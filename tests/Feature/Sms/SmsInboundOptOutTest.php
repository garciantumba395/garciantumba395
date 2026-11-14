<?php

namespace Tests\Feature\Sms;

use App\Domain\Consent\ConsentService;
use App\Domain\Sms\Webhooks\SmsWebhookEvent;
use App\Domain\Sms\Webhooks\SmsWebhookProcessor;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SignsFakeWebhooks;
use Tests\TestCase;

class SmsInboundOptOutTest extends TestCase
{
    use RefreshDatabase;
    use SignsFakeWebhooks;

    private const PHONE = '+243810000001';

    private Company $a;

    private Company $b;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        $this->configureWebhookSecret();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        $this->c = Company::createFor(User::factory()->create(), 'C');
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    private function inbound(string $text, ?string $eventId = null, string $from = self::PHONE): TestResponse
    {
        static $i = 0;

        return $this->postWebhook(['event_id' => $eventId ?? 'in-'.(++$i), 'type' => 'inbound', 'from' => $from, 'text' => $text]);
    }

    /** @return array<int,int> ids des entreprises ayant une désinscription ACTIVE pour ce numéro */
    private function optedOutCompanies(string $phone = self::PHONE): array
    {
        return Suppression::withoutGlobalScopes()->where('phone', $phone)->whereNull('lifted_at')->orderBy('company_id')->pluck('company_id')->all();
    }

    public function test_stop_opts_out_the_number_in_every_company_that_really_sent_to_it_and_only_those(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'delivered');                                        // réel : oui
        $this->makeMessage($this->b, self::PHONE, 'sent');                                             // réel : oui
        $this->makeMessage($this->c, self::PHONE, 'sent', simulated: true);                            // simulé : non
        $d = Company::createFor(User::factory()->create(), 'D');
        $this->makeMessage($d, '+243810000099', 'sent');                                               // autre numéro : non
        $e = Company::createFor(User::factory()->create(), 'E');                                       // jamais contactée : non

        $this->inbound('STOP')->assertOk()->assertJson(['status' => 'optout']);

        $this->assertSame([$this->a->id, $this->b->id], $this->optedOutCompanies());
        $this->assertSame([], $this->optedOutCompanies('+243810000099'));
        $this->assertSame(0, Suppression::withoutGlobalScopes()->where('company_id', $e->id)->count());
        $this->assertSame('sms_keyword', Suppression::withoutGlobalScopes()->where('company_id', $this->a->id)->value('source'));
    }

    public function test_opt_out_revokes_consent_only_in_concerned_companies(): void
    {
        foreach ([$this->a, $this->c] as $company) {
            app(CurrentCompany::class)->runAs($company, function () {
                $contact = Contact::create(['phone' => self::PHONE]);
                app(ConsentService::class)->record($contact, 'web_form', 'Case cochée', now()->subDay());
            });
        }
        $this->makeMessage($this->a, self::PHONE, 'sent');
        $this->makeMessage($this->c, self::PHONE, 'sent', simulated: true);

        $this->inbound('stop')->assertOk();

        $active = fn (Company $co) => Consent::withoutGlobalScopes()->where('company_id', $co->id)->whereNull('revoked_at')->count();
        $this->assertSame(0, $active($this->a));
        $this->assertSame(1, $active($this->c)); // entreprise non concernée : intacte
    }

    #[DataProvider('stopVariants')]
    public function test_keyword_variants_are_recognised(string $text): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound($text)->assertJson(['status' => 'optout']);

        $this->assertSame([$this->a->id], $this->optedOutCompanies());
    }

    public static function stopVariants(): array
    {
        return [['STOP'], ['stop'], ['Stop'], ['  stop  '], ["\n\tSTOP \r\n"], ['STOP.'], ['stop !'], ['STÔP'], ['sTôP']];
    }

    #[DataProvider('ignoredTexts')]
    public function test_other_messages_are_ignored(string $text): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound($text)->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame([], $this->optedOutCompanies());
    }

    public static function ignoredTexts(): array
    {
        return [['Merci beaucoup'], ['stop spam'], ['please stop'], ['STOPPER'], ['S T O P'], ['STOP2'], [''], ['   '], ['...']];
    }

    public function test_keywords_are_configurable_with_accents_and_inner_spaces_normalised(): void
    {
        config(['sms.inbound.optout_keywords' => ['ARRÊT', 'désinscrire  moi']]);
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound('stop')->assertJson(['status' => 'ignored']);         // STOP n'est plus dans la liste
        $this->inbound('arret')->assertJson(['status' => 'optout']);
        $this->inbound('DESINSCRIRE MOI', 'in-x2')->assertJson(['status' => 'optout']);
        $this->inbound('desinscrire    moi', 'in-x3')->assertJson(['status' => 'optout']);
    }

    public function test_empty_keyword_list_never_opts_out(): void
    {
        config(['sms.inbound.optout_keywords' => []]);
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound('STOP')->assertJson(['status' => 'ignored']);
        $this->inbound('')->assertJson(['status' => 'ignored']);

        $this->assertSame([], $this->optedOutCompanies());
    }

    public function test_default_keyword_list_is_minimal(): void
    {
        $this->assertSame(['STOP'], (require config_path('sms.php'))['inbound']['optout_keywords']);
    }

    public function test_simulated_messages_never_trigger_a_real_opt_out(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent', simulated: true);
        $this->makeMessage($this->a, self::PHONE, 'delivered', simulated: true);

        $this->inbound('STOP')->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame([], $this->optedOutCompanies());
    }

    public function test_messages_never_sent_do_not_count(): void
    {
        foreach (['pending', 'blocked', 'sending', 'failed'] as $status) {
            $this->makeMessage($this->a, self::PHONE, $status);
        }

        $this->inbound('STOP')->assertJson(['status' => 'ignored']);

        $this->assertSame([], $this->optedOutCompanies());
    }

    public function test_unknown_number_is_ignored_without_side_effect(): void
    {
        $this->inbound('STOP', from: '+243819999999')->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(0, Suppression::withoutGlobalScopes()->count());
        $this->assertSame(1, DB::table('webhook_events')->count());
    }

    public function test_window_is_respected_and_configurable(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent', createdAt: now()->subDays(29));
        $this->makeMessage($this->b, self::PHONE, 'sent', createdAt: now()->subDays(31));

        $this->inbound('STOP')->assertJson(['status' => 'optout']);
        $this->assertSame([$this->a->id], $this->optedOutCompanies());

        config(['sms.inbound.window_days' => 60]);
        $this->inbound('STOP')->assertJson(['status' => 'optout']);
        $this->assertSame([$this->a->id, $this->b->id], $this->optedOutCompanies());
    }

    public function test_idempotent_for_replays_and_for_repeated_keywords(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound('STOP', 'same')->assertJson(['status' => 'optout']);
        $this->inbound('STOP', 'same')->assertJson(['status' => 'duplicate']);   // rejeu du même événement
        $this->inbound('STOP', 'other')->assertJson(['status' => 'optout']);     // nouveau SMS STOP : pas de doublon

        $this->assertSame(1, Suppression::withoutGlobalScopes()->where('company_id', $this->a->id)->count());
    }

    public function test_free_text_is_never_stored(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent');

        $this->inbound('Bonjour, je suis Jean Dupont, secret-xyz-123')->assertJson(['status' => 'ignored']);
        $this->inbound('stop', 'in-stop')->assertJson(['status' => 'optout']);

        $dump = json_encode([DB::table('webhook_events')->get(), DB::table('suppressions')->get(), DB::table('sms_messages')->get()]);
        $this->assertStringNotContainsString('secret-xyz-123', $dump);
        $this->assertStringNotContainsString('Dupont', $dump);
    }

    public function test_processor_restores_the_previous_company_context(): void
    {
        $this->makeMessage($this->b, self::PHONE, 'sent');
        $current = app(CurrentCompany::class);
        $current->set($this->a);

        app(SmsWebhookProcessor::class)->process('fake', SmsWebhookEvent::inbound('e', self::PHONE, 'STOP'));

        $this->assertSame($this->a->id, $current->id());
        $this->assertSame([$this->b->id], $this->optedOutCompanies());
    }

    public function test_processor_works_without_any_active_company_context(): void
    {
        $this->makeMessage($this->a, self::PHONE, 'sent');
        $current = app(CurrentCompany::class);
        $current->forget();

        app(SmsWebhookProcessor::class)->process('fake', SmsWebhookEvent::inbound('e', self::PHONE, 'STOP'));

        $this->assertNull($current->id()); // aucune fuite de contexte
        $this->assertSame([$this->a->id], $this->optedOutCompanies());
    }
}
