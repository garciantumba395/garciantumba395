<?php

namespace Tests\Feature\Sms;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsStatus;
use App\Livewire\Admin\Companies as AdminCompanies;
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

class SendingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Company $co;

    private User $owner;

    private SmsDispatcher $dispatcher;

    private CountingLiveProvider $live;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Acme');
        app(CreditService::class)->credit($this->co, 60000000, 'seed'); // 10 000 crédits
        app(CurrentCompany::class)->set($this->co->fresh());
        $this->dispatcher = app(SmsDispatcher::class);
        $this->live = new CountingLiveProvider;
        config(['sms.sending.min_sample' => 5, 'sms.sending.probation_daily_limit' => 3]);
    }

    private function goLive(): void
    {
        app(SmsManager::class)->extend('live-test', fn () => $this->live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true]);
    }

    private function contact(int $i): Contact
    {
        $c = Contact::create(['phone' => '+24381'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT)]);
        app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());

        return $c;
    }

    private function send(int $i): SmsMessage
    {
        return $this->dispatcher->dispatch($this->contact($i), 'Bonjour', "k$i");
    }

    private function company(): Company
    {
        return Company::find($this->co->id);
    }

    public function test_new_company_starts_in_probation_and_status_is_not_mass_assignable(): void
    {
        $this->assertSame(SendingStatus::Probation, $this->company()->sending_status);
        $c = Company::create(['name' => 'X', 'slug' => 'x', 'sending_status' => 'validated', 'daily_send_limit' => 999999]);
        $this->assertSame(SendingStatus::Probation, $c->fresh()->sending_status);
        $this->assertNull($c->fresh()->daily_send_limit);
    }

    public function test_simulation_is_never_limited_and_never_validates_the_company(): void
    {
        config(['sms.sending.probation_daily_limit' => 1]);

        for ($i = 1; $i <= 8; $i++) {
            $this->assertSame(SmsStatus::Sent, $this->send($i)->status);
        }

        $this->assertSame(SendingStatus::Probation, $this->company()->sending_status); // 8 envois simulés ≥ échantillon, mais sans effet
    }

    public function test_live_probation_enforces_daily_limit_without_debit_or_send(): void
    {
        $this->goLive();
        $this->send(1);
        $this->send(2);
        $this->send(3);
        $balance = app(CreditService::class)->balance($this->co);

        $m = $this->send(4);

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertSame('daily_limit', $m->blocked_reason);
        $this->assertSame(3, $this->live->calls);
        $this->assertSame($balance, app(CreditService::class)->balance($this->co));
    }

    public function test_daily_limit_resets_the_next_day(): void
    {
        $this->goLive();
        foreach ([1, 2, 3] as $i) {
            $this->send($i);
        }
        $this->assertSame('daily_limit', $this->send(4)->blocked_reason);

        $this->travel(1)->days();
        $this->assertSame(SmsStatus::Sent, $this->send(5)->status);
    }

    public function test_blocked_messages_do_not_count_toward_the_limit(): void
    {
        $this->goLive();
        $c = Contact::create(['phone' => '+243819999999']); // sans consentement
        for ($i = 1; $i <= 5; $i++) {
            $this->dispatcher->dispatch($c, 'Bonjour', "b$i");
        }
        $this->assertSame(SmsStatus::Sent, $this->send(1)->status);
        $this->assertSame(1, $this->live->calls);
    }

    public function test_company_override_beats_defaults(): void
    {
        $this->goLive();
        $this->company()->forceFill(['daily_send_limit' => 1])->save();

        $this->assertSame(SmsStatus::Sent, $this->send(1)->status);
        $this->assertSame('daily_limit', $this->send(2)->blocked_reason);

        $this->company()->forceFill(['daily_send_limit' => 0])->save();
        $this->assertSame('daily_limit', $this->send(3)->blocked_reason);
    }

    public function test_suspended_company_cannot_send_real_but_can_simulate(): void
    {
        $this->company()->forceFill(['sending_status' => 'suspended'])->save();

        $this->assertSame(SmsStatus::Sent, $this->send(1)->status); // simulation

        $this->goLive();
        $m = $this->send(2);
        $this->assertSame('suspended', $m->blocked_reason);
        $this->assertSame(0, $this->live->calls);
    }

    public function test_healthy_real_sends_validate_the_company_automatically(): void
    {
        $this->goLive();
        config(['sms.sending.probation_daily_limit' => 100, 'sms.sending.validated_daily_limit' => null]);

        for ($i = 1; $i <= 4; $i++) {
            $this->send($i);
        }
        $this->assertSame(SendingStatus::Probation, $this->company()->sending_status); // échantillon insuffisant

        $this->send(5);
        $co = $this->company();
        $this->assertSame(SendingStatus::Validated, $co->sending_status);
        $this->assertStringContainsString('Validation automatique', $co->sending_status_reason);

        config(['sms.sending.probation_daily_limit' => 1]); // la limite de démarrage ne s'applique plus
        $this->assertSame(SmsStatus::Sent, $this->send(6)->status);
    }

    public function test_high_failure_rate_suspends_automatically(): void
    {
        $this->goLive();
        config(['sms.sending.probation_daily_limit' => 100]);
        $contacts = array_map(fn ($i) => $this->contact($i), [1, 2, 3, 4, 5]);
        $this->live->rejectNumbers = array_map(fn ($c) => $c->phone, array_slice($contacts, 0, 4)); // 4 refus sur 5

        foreach ($contacts as $i => $c) {
            $this->dispatcher->dispatch($c, 'Bonjour', "f$i");
        }

        $this->assertSame(SendingStatus::Suspended, $this->company()->sending_status);
        $this->assertStringContainsString("taux d'échec", $this->company()->sending_status_reason);
        $this->assertSame('suspended', $this->send(50)->blocked_reason);
    }

    public function test_high_optout_rate_suspends_automatically(): void
    {
        $this->goLive();
        config(['sms.sending.probation_daily_limit' => 100, 'sms.sending.min_sample' => 10, 'sms.sending.max_optout_rate' => 0.1]);
        $contacts = [];
        for ($i = 1; $i <= 9; $i++) {
            $contacts[$i] = $this->contact($i);
            $this->dispatcher->dispatch($contacts[$i], 'Bonjour', "k$i");
        }
        $this->assertSame(SendingStatus::Probation, $this->company()->sending_status);

        app(ConsentService::class)->optOut($contacts[1]->phone);
        app(ConsentService::class)->optOut($contacts[2]->phone); // 2 désinscriptions sur 10 envois = 20 % > 10 %
        $this->send(10);

        $this->assertSame(SendingStatus::Suspended, $this->company()->sending_status);
        $this->assertStringContainsString('désinscription', $this->company()->sending_status_reason);
    }

    public function test_failed_delivery_reports_received_before_evaluation_are_counted(): void
    {
        $this->goLive();
        config(['sms.sending.probation_daily_limit' => 100, 'sms.sending.min_sample' => 5, 'sms.sending.max_failure_rate' => 0.3]);
        $ids = [];
        for ($i = 1; $i <= 4; $i++) {
            $ids[] = $this->send($i)->provider_message_id;
        }
        // Accusés « échec » reçus avant que l'échantillon minimum soit atteint : ils comptent dans l'évaluation.
        $this->dispatcher->recordDeliveryReport('live-test', $ids[0], SmsStatus::Failed, 'X');
        $this->dispatcher->recordDeliveryReport('live-test', $ids[1], SmsStatus::Failed, 'X');
        $this->send(5);

        $this->assertSame(SendingStatus::Suspended, $this->company()->sending_status); // 2/5 = 40 % > 30 %
    }

    public function test_only_real_messages_count_in_metrics(): void
    {
        $this->send(1); // simulé
        $this->send(2);
        $this->goLive();
        $this->send(3);

        $m = app(SendingPolicy::class)->metrics($this->co->id, now()->subDay());
        $this->assertSame(1, $m['sample']);
    }

    public function test_admin_can_force_status_and_limit_with_reason_but_owner_cannot(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        Livewire::actingAs($this->owner)->test(AdminCompanies::class)->assertForbidden();

        Livewire::actingAs($admin)->test(AdminCompanies::class)->set('companyId', $this->co->id)
            ->set('sendingStatus', 'validated')->set('sendingReason', '')->call('updateSending')->assertHasErrors('sendingReason')
            ->set('sendingReason', 'Client vérifié par appel')->set('dailyLimit', '500')->call('updateSending')->assertHasNoErrors();

        $co = $this->company();
        $this->assertSame(SendingStatus::Validated, $co->sending_status);
        $this->assertSame(500, $co->daily_send_limit);
        $this->assertSame('Client vérifié par appel', $co->sending_status_reason);
    }

    public function test_messages_page_shows_policy_banners_only_in_real_mode(): void
    {
        $this->actingAs($this->owner)->get('/messages')->assertDontSee('Période de démarrage');

        $this->goLive();
        $this->actingAs($this->owner)->get('/messages')->assertSee('Période de démarrage');

        $this->company()->forceFill(['sending_status' => 'suspended', 'sending_status_reason' => 'Taux d\'échec élevé.'])->save();
        $this->actingAs($this->owner)->get('/messages')->assertSee('Envoi réel suspendu');
    }
}
