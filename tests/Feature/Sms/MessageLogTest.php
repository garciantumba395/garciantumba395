<?php

namespace Tests\Feature\Sms;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Enums\CompanyRole;
use App\Livewire\Messages\Index;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CountingLiveProvider;
use Tests\TestCase;

class MessageLogTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $ua;

    private User $ub;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->ua = User::factory()->create();
        $this->ub = User::factory()->create();
        $this->a = Company::createFor($this->ua, 'A');
        $this->b = Company::createFor($this->ub, 'B');
        app(CreditService::class)->credit($this->a, 600000, 'seed:a'); // 100 crédits
        app(CreditService::class)->credit($this->b, 600000, 'seed:b');
    }

    private function in(Company $c, callable $fn): mixed
    {
        $cur = app(CurrentCompany::class);
        $cur->set($c);
        try {
            return $fn();
        } finally {
            $cur->forget();
        }
    }

    private function eligibleContact(Company $c, string $phone = '+243812345678'): Contact
    {
        return $this->in($c, function () use ($phone) {
            $k = Contact::create(['phone' => $phone]);
            app(ConsentService::class)->record($k, 'paper', 'x', now()->subDay());

            return $k;
        });
    }

    public function test_page_shows_simulation_banner_by_default(): void
    {
        $this->actingAs($this->ua)->get('/messages')->assertOk()->assertSee('Mode simulation')->assertDontSee('ENVOI RÉEL ACTIVÉ');
    }

    public function test_page_shows_real_mode_banner_only_when_live_and_enabled(): void
    {
        app(SmsManager::class)->extend('live-test', fn () => new CountingLiveProvider);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true]);

        $this->actingAs($this->ua)->get('/messages')->assertSee('ENVOI RÉEL ACTIVÉ');
    }

    public function test_test_send_logs_a_simulated_message(): void
    {
        $c = $this->eligibleContact($this->a);

        Livewire::actingAs($this->ua)->test(Index::class)->set('contactId', $c->id)->set('body', 'Message de test')
            ->call('sendTest')->assertHasNoErrors()->assertSee('Simulation')->assertSee('Message de test');

        $m = SmsMessage::withoutGlobalScopes()->first();
        $this->assertTrue($m->simulated);
        $this->assertSame($this->ua->id, $m->created_by);
        Http::assertNothingSent();
    }

    public function test_double_submit_with_same_attempt_key_sends_once(): void
    {
        $c = $this->eligibleContact($this->a);
        $page = Livewire::actingAs($this->ua)->test(Index::class)->set('contactId', $c->id)->set('body', 'Test');
        $key = $page->get('attemptKey');

        $page->call('sendTest');
        // Rejeu de la même tentative (ex. double clic / requête rejouée) : même clé d'idempotence.
        $this->in($this->a, fn () => app(SmsDispatcher::class)->dispatch(Contact::first(), 'Test', 'test:'.$key, null, $this->ua->id));

        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
        $this->assertNotSame($key, $page->get('attemptKey')); // nouvelle clé pour la tentative suivante
    }

    public function test_blocked_message_is_shown_with_reason(): void
    {
        $c = $this->in($this->a, fn () => Contact::create(['phone' => '+243812345678']));

        Livewire::actingAs($this->ua)->test(Index::class)->set('contactId', $c->id)->set('body', 'Test')
            ->call('sendTest')->assertSee('Bloqué')->assertSee('sans consentement');
    }

    public function test_only_admins_can_send_tests(): void
    {
        $member = User::factory()->create();
        $this->a->users()->attach($member, ['role' => CompanyRole::Member->value]);
        $c = $this->eligibleContact($this->a);

        Livewire::actingAs($member)->test(Index::class)->assertDontSee('Envoi de test')
            ->set('contactId', $c->id)->set('body', 'x')->call('sendTest')->assertForbidden();
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_log_is_isolated_and_cannot_target_another_companys_contact(): void
    {
        $mine = $this->eligibleContact($this->a, '+243811111111');
        $foreign = $this->eligibleContact($this->b, '+243822222222');
        $this->in($this->b, fn () => app(SmsDispatcher::class)->dispatch($foreign, 'Secret de B', 'k'));

        Livewire::actingAs($this->ua)->test(Index::class)->assertDontSee('Secret de B')
            ->set('contactId', $foreign->id)->set('body', 'x')->call('sendTest')->assertNotFound();
        $this->assertNotNull($mine);
        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_status_filter_and_guest_redirect(): void
    {
        $this->get('/messages')->assertRedirect('/connexion');

        $c = $this->eligibleContact($this->a);
        $this->in($this->a, fn () => app(SmsDispatcher::class)->dispatch($c, 'Livré ok', 'k1'));
        Livewire::actingAs($this->ua)->test(Index::class)->set('status', 'failed')->assertDontSee('Livré ok')
            ->set('status', 'sent')->assertSee('Livré ok');
    }
}
