<?php

namespace Tests\Feature\Sms;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\FakeSmsProvider;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsSendingDisabledException;
use App\Domain\Sms\SmsStatus;
use App\Domain\Sms\UnknownSmsProviderException;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use Tests\Support\CountingLiveProvider;
use Tests\TestCase;

class SmsDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private SmsDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); // toute requête HTTP sortante fait échouer le test
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        app(CreditService::class)->credit($this->a, 600000, 'seed:a'); // 100 crédits
        app(CreditService::class)->credit($this->b, 600000, 'seed:b');
        app(CurrentCompany::class)->set($this->a);
        $this->dispatcher = app(SmsDispatcher::class);
    }

    private function eligible(string $phone = '+243812345678'): Contact
    {
        $c = Contact::create(['phone' => $phone]);
        app(ConsentService::class)->record($c, 'web_form', 'Case cochée', now()->subDay());

        return $c;
    }

    private function fake(): FakeSmsProvider
    {
        return app(SmsManager::class)->provider();
    }

    // --- Fournisseur fictif et activation explicite ---------------------------------

    public function test_default_configuration_is_fake_and_never_live(): void
    {
        $this->assertSame('fake', config('sms.provider'));
        $this->assertFalse(config('sms.sending_enabled'));
        $this->assertInstanceOf(FakeSmsProvider::class, app(SmsProvider::class));
        $this->assertFalse(app(SmsManager::class)->isLiveMode());
    }

    public function test_fake_send_makes_no_network_call_and_is_flagged_simulated(): void
    {
        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Sent, $m->status);
        $this->assertTrue($m->simulated);
        $this->assertSame('fake', $m->provider);
        $this->assertCount(1, $this->fake()->sentRequests());
        Http::assertNothingSent();
    }

    public function test_live_provider_cannot_be_resolved_without_explicit_activation(): void
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => false]);

        $this->expectException(SmsSendingDisabledException::class);
        try {
            app(SmsProvider::class);
        } finally {
            $this->assertSame(0, $live->calls);
        }
    }

    public function test_dispatch_with_live_provider_not_enabled_sends_nothing_and_writes_nothing(): void
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => false]);
        $c = $this->eligible();

        try {
            $this->dispatcher->dispatch($c, 'Bonjour', 'k1');
            $this->fail('Exception attendue');
        } catch (SmsSendingDisabledException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, $live->calls);
        $this->assertSame(0, SmsMessage::count());
        $this->assertFalse(app(SmsManager::class)->isLiveMode());
    }

    public function test_live_provider_works_only_when_both_switches_are_on_and_is_flagged_real(): void
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true]);

        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');

        $this->assertSame(1, $live->calls);
        $this->assertFalse($m->simulated);
        $this->assertTrue(app(SmsManager::class)->isLiveMode());
    }

    public function test_unknown_provider_is_refused(): void
    {
        config(['sms.provider' => 'inconnu']);
        $this->expectException(UnknownSmsProviderException::class);
        app(SmsProvider::class);
    }

    // --- Consentement vérifié à chaque envoi -------------------------------------

    public function test_contact_without_consent_is_blocked_and_provider_not_called(): void
    {
        $c = Contact::create(['phone' => '+243812345678']);

        $m = $this->dispatcher->dispatch($c, 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertSame('no_consent', $m->blocked_reason);
        $this->assertCount(0, $this->fake()->sentRequests());
    }

    public function test_suppression_added_after_preparation_blocks_the_send(): void
    {
        $c = $this->eligible();
        app(ConsentService::class)->optOut($c->phone); // désinscription avant l'envoi

        $m = $this->dispatcher->dispatch($c, 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertSame('suppressed', $m->blocked_reason);
        $this->assertCount(0, $this->fake()->sentRequests());
    }

    public function test_eligibility_is_rechecked_on_every_send_with_a_stale_contact_instance(): void
    {
        $c = $this->eligible();
        $stale = Contact::find($c->id);
        $this->dispatcher->dispatch($stale, 'Un', 'k1');

        app(ConsentService::class)->optOut($c->phone);
        $m = $this->dispatcher->dispatch($stale, 'Deux', 'k2');

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertCount(1, $this->fake()->sentRequests());
    }

    // --- Doublons -----------------------------------------------------------------

    public function test_same_idempotency_key_sends_only_once(): void
    {
        $c = $this->eligible();

        $first = $this->dispatcher->dispatch($c, 'Bonjour', 'camp1:c1');
        $again = $this->dispatcher->dispatch($c, 'Bonjour', 'camp1:c1');
        $third = $this->dispatcher->dispatch($c, 'Texte différent', 'camp1:c1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, SmsMessage::count());
        $this->assertCount(1, $this->fake()->sentRequests());
        $this->assertSame(1, $first->fresh()->attempts);
    }

    public function test_message_already_claimed_by_another_worker_is_not_sent_again(): void
    {
        $c = $this->eligible();
        SmsMessage::create([
            'contact_id' => $c->id, 'idempotency_key' => 'k1', 'phone' => $c->phone, 'body' => 'x',
            'status' => SmsStatus::Sending, 'provider' => 'fake',
        ]);

        $m = $this->dispatcher->dispatch($c, 'x', 'k1');

        $this->assertSame(SmsStatus::Sending, $m->status);
        $this->assertCount(0, $this->fake()->sentRequests());
    }

    public function test_same_key_in_two_companies_are_independent(): void
    {
        $this->dispatcher->dispatch($this->eligible(), 'A', 'k1');
        app(CurrentCompany::class)->set($this->b);
        $this->dispatcher->dispatch($this->eligible(), 'B', 'k1');

        $this->assertSame(2, SmsMessage::withoutGlobalScopes()->count());
        $this->assertSame(1, SmsMessage::count());
    }

    public function test_database_enforces_unique_key_per_company(): void
    {
        $this->dispatcher->dispatch($this->eligible(), 'A', 'k1');
        $this->expectException(QueryException::class);
        \DB::table('sms_messages')->insert([
            'company_id' => $this->a->id, 'idempotency_key' => 'k1', 'phone' => '+243800000000', 'body' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- Échecs --------------------------------------------------------------------

    public function test_definitive_rejection_is_recorded_as_failed(): void
    {
        config(['sms.fake.reject_numbers' => ['+243812345678']]);

        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Failed, $m->status);
        $this->assertSame('FAKE_REJECTED', $m->error_code);
    }

    public function test_transport_error_leaves_unknown_status_and_is_never_resent_automatically(): void
    {
        config(['sms.fake.throw_numbers' => ['+243812345678']]);
        $c = $this->eligible();

        $m = $this->dispatcher->dispatch($c, 'Bonjour', 'k1');
        $this->assertSame(SmsStatus::Unknown, $m->status);

        config(['sms.fake.throw_numbers' => []]);
        $retry = $this->dispatcher->dispatch($c, 'Bonjour', 'k1'); // même clé : aucun renvoi
        $this->assertSame(SmsStatus::Unknown, $retry->status);
        $this->assertCount(0, $this->fake()->sentRequests());
    }

    // --- Validation et isolation -----------------------------------------------------

    public function test_invalid_inputs_are_rejected_before_anything_is_written(): void
    {
        $c = $this->eligible();
        foreach ([['', 'k'], ['ok', ''], [str_repeat('a', 1601), 'k'], ['ok', str_repeat('k', 121)]] as [$body, $key]) {
            try {
                $this->dispatcher->dispatch($c, $body, $key);
                $this->fail('Exception attendue');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_cannot_send_to_another_companys_contact(): void
    {
        app(CurrentCompany::class)->set($this->b);
        $foreign = $this->eligible();
        app(CurrentCompany::class)->set($this->a);

        $this->expectException(LogicException::class);
        $this->dispatcher->dispatch($foreign, 'Bonjour', 'k1');
    }

    public function test_message_log_survives_contact_deletion(): void
    {
        $c = $this->eligible();
        $this->dispatcher->dispatch($c, 'Bonjour', 'k1');
        $c->delete();

        $this->assertSame('+243812345678', SmsMessage::first()->phone);
    }

    // --- Accusés de réception -----------------------------------------------------------

    public function test_delivery_report_updates_status_without_going_backwards(): void
    {
        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');
        $pid = $m->provider_message_id;

        app(CurrentCompany::class)->forget(); // un webhook n'a pas de contexte d'entreprise
        $this->dispatcher->recordDeliveryReport('fake', $pid, SmsStatus::Delivered);
        $this->assertSame(SmsStatus::Delivered, SmsMessage::withoutGlobalScopes()->first()->status);

        $this->dispatcher->recordDeliveryReport('fake', $pid, SmsStatus::Failed, 'X'); // accusé tardif contradictoire
        $this->assertSame(SmsStatus::Delivered, SmsMessage::withoutGlobalScopes()->first()->status);

        $this->assertNull($this->dispatcher->recordDeliveryReport('fake', 'inconnu', SmsStatus::Delivered));
    }

    public function test_delivery_report_only_accepts_final_statuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->dispatcher->recordDeliveryReport('fake', 'x', SmsStatus::Sent);
    }
}
