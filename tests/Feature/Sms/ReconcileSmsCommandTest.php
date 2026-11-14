<?php

namespace Tests\Feature\Sms;

use App\Console\Commands\ReconcileSms;
use App\Models\Company;
use App\Models\CreditLedgerEntry;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsFakeWebhooks;
use Tests\TestCase;

class ReconcileSmsCommandTest extends TestCase
{
    use RefreshDatabase;
    use SignsFakeWebhooks;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
    }

    private function stale(Company $co, string $phone, string $status = 'sending', bool $simulated = false, int $minutesAgo = 120): SmsMessage
    {
        return $this->makeMessage($co, $phone, $status, $simulated, updatedAt: now()->subMinutes($minutesAgo), createdAt: now()->subMinutes($minutesAgo + 5));
    }

    /** @return array{0:int,1:string} */
    private function reconcile(array $options = []): array
    {
        $code = Artisan::call('sms:reconcile', $options);

        return [$code, Artisan::output()];
    }

    public function test_nothing_to_reconcile_exits_zero(): void
    {
        $this->stale($this->a, '+243810000001', 'sending', false, 5);   // récent
        $this->stale($this->a, '+243810000002', 'sent');                 // statut final côté plateforme

        [$code, $out] = $this->reconcile();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('OK', $out);
    }

    public function test_lists_stale_real_messages_with_masked_numbers_and_exit_code_one(): void
    {
        $this->stale($this->a, '+243810000001', 'sending');
        $this->stale($this->a, '+243810000002', 'unknown');
        $this->stale($this->a, '+243810000003', 'sending', false, 5); // sous le seuil

        [$code, $out] = $this->reconcile();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Entreprise #'.$this->a->id.' — 2 message(s) RÉELS', $out);
        $this->assertStringContainsString('+243*******01', $out);
        $this->assertStringContainsString('+243*******02', $out);
        $this->assertStringNotContainsString('+243*******03', $out);
        // Aucun numéro complet, aucun contenu de message.
        $this->assertStringNotContainsString('810000001', $out);
        $this->assertStringNotContainsString('810000002', $out);
        $this->assertStringNotContainsString('Corps de test', $out);
    }

    public function test_threshold_is_configurable_by_option_and_config(): void
    {
        $this->stale($this->a, '+243810000001', 'sending', false, 30);

        $this->assertSame(0, $this->reconcile()[0]);                       // défaut 60 min : pas encore signalé
        $this->assertSame(1, $this->reconcile(['--minutes' => 10])[0]);    // option
        config(['sms.reconcile.stale_minutes' => 10]);
        $this->assertSame(1, $this->reconcile()[0]);                       // configuration
    }

    public function test_simulated_messages_are_excluded_by_default_and_never_affect_the_exit_code(): void
    {
        $this->stale($this->a, '+243810000001', 'unknown', true);

        [$code, $out] = $this->reconcile();
        $this->assertSame(0, $code);
        $this->assertStringNotContainsString('+243*******01', $out);

        [$code, $out] = $this->reconcile(['--include-simulated' => true]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('SIMULÉS', $out);
        $this->assertStringContainsString('+243*******01', $out);
        $this->assertStringNotContainsString('RÉELS', $out);
    }

    public function test_real_and_simulated_are_kept_apart(): void
    {
        $this->stale($this->a, '+243810000001', 'unknown', false);
        $this->stale($this->a, '+243810000002', 'unknown', true);

        [$code, $out] = $this->reconcile(['--include-simulated' => true]);

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/1 message\(s\) RÉELS.*\+243\*{7}01.*1 message\(s\) SIMULÉS.*\+243\*{7}02/s', $out);
        $this->assertDoesNotMatchRegularExpression('/RÉELS.*\+243\*{7}02.*SIMULÉS/s', $out);
    }

    public function test_companies_are_reported_separately_and_can_be_filtered(): void
    {
        $this->stale($this->a, '+243810000001');
        $this->stale($this->b, '+243820000002');

        [, $out] = $this->reconcile();
        $this->assertMatchesRegularExpression('/Entreprise #'.$this->a->id.'.*\+243\*{7}01.*Entreprise #'.$this->b->id.'.*\+243\*{7}02/s', $out);
        $this->assertDoesNotMatchRegularExpression('/Entreprise #'.$this->a->id.'[^#]*\+243\*{7}02/s', $out);

        [$code, $out] = $this->reconcile(['--company' => $this->a->id]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('+243*******01', $out);
        $this->assertStringNotContainsString('+243*******02', $out);
    }

    public function test_is_read_only_and_leaves_no_company_context(): void
    {
        $message = $this->stale($this->a, '+243810000001', 'unknown');
        $before = SmsMessage::withoutGlobalScopes()->get()->map->getAttributes()->all();
        $ledger = CreditLedgerEntry::withoutGlobalScopes()->count();

        $this->reconcile();

        $this->assertSame($before, SmsMessage::withoutGlobalScopes()->get()->map->getAttributes()->all());
        $this->assertSame($ledger, CreditLedgerEntry::withoutGlobalScopes()->count());
        $this->assertSame('unknown', $message->fresh()->status->value);
        $this->assertNull(app(CurrentCompany::class)->id());
    }

    public function test_limit_truncates_the_listing_but_not_the_count(): void
    {
        foreach (range(1, 3) as $i) {
            $this->stale($this->a, '+24381000000'.$i);
        }

        [$code, $out] = $this->reconcile(['--limit' => 2]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('3 message(s) RÉELS', $out);
        $this->assertStringContainsString('1 autre(s) non affiché(s)', $out);
    }

    public function test_invalid_options_exit_with_code_two(): void
    {
        $this->assertSame(2, $this->reconcile(['--minutes' => 'abc'])[0]);
        $this->assertSame(2, $this->reconcile(['--minutes' => '0'])[0]);
        $this->assertSame(2, $this->reconcile(['--limit' => '-1'])[0]);
        $this->assertSame(2, $this->reconcile(['--company' => 'x'])[0]);
    }

    public function test_mask_never_reveals_more_than_prefix_and_two_digits(): void
    {
        $this->assertSame('+243*******01', ReconcileSms::maskPhone('+243810000001'));
        $this->assertSame('********', ReconcileSms::maskPhone('12345678'));
        $this->assertSame('', ReconcileSms::maskPhone(''));
    }
}
