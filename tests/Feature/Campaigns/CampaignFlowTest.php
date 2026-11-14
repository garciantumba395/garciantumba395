<?php

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsStatus;
use App\Jobs\PrepareCampaign;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\CreditLedgerEntry;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Support\CountingLiveProvider;
use Tests\TestCase;

class CampaignFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $ua;

    private CampaignService $svc;

    private CreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->ua = User::factory()->create();
        $this->a = Company::createFor($this->ua, 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        // Art. 66 : l'envoi RÉEL exige l'identité de l'expéditeur (la simulation non).
        $this->a->update(['legal_name' => 'A SARL', 'legal_address' => '1 avenue de test, Kinshasa', 'legal_email' => 'contact@a.example.test']);
        $this->credits = app(CreditService::class);
        $this->credits->credit($this->a, 6000000, 'seed:a'); // 1000 crédits
        $this->credits->credit($this->b, 6000000, 'seed:b');
        app(CurrentCompany::class)->set($this->a);
        $this->svc = app(CampaignService::class);
    }

    private function contact(string $phone, bool $consent = true): Contact
    {
        $c = Contact::create(['phone' => $phone]);
        if ($consent) {
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());
        }

        return $c;
    }

    private function campaign(?ContactGroup $group = null, string $body = 'Promo'): Campaign
    {
        $c = new Campaign(['name' => 'Test', 'body' => $body, 'contact_group_id' => $group?->id]);
        $c->created_by = $this->ua->id;
        $c->save();

        return $c;
    }

    /** Groupe de 3 contacts : 2 avec consentement, 1 sans. */
    private function groupOfThree(): ContactGroup
    {
        $g = ContactGroup::create(['name' => 'G']);
        $g->addContacts([
            $this->contact('+243811000001')->id, $this->contact('+243811000002')->id, $this->contact('+243811000003', false)->id,
        ]);

        return $g;
    }

    private function live(): CountingLiveProvider
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true, 'sms.sending.probation_daily_limit' => 100000]);

        return $live;
    }

    // --- Flux complet -------------------------------------------------------------

    public function test_full_flow_sends_only_to_consenting_contacts_and_completes(): void
    {
        $campaign = $this->campaign($this->groupOfThree());

        $this->svc->launch($campaign);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame(1, $campaign->excluded_count);
        $this->assertSame(2, SmsMessage::where('status', SmsStatus::Sent->value)->count());
        $this->assertSame(2, CampaignMessage::whereNotNull('processed_at')->count());
        $this->assertSame(6000000 - 2 * 5000, $this->credits->balance($this->a)); // 2 envois d'un segment
        $this->assertSame(2, SmsMessage::where('simulated', true)->count());
    }

    public function test_all_contacts_target_when_no_group(): void
    {
        $this->contact('+243811000001');
        $this->contact('+243811000002');
        $this->contact('+243811000003', false);
        $campaign = $this->campaign();

        $this->svc->launch($campaign);

        $this->assertSame(2, $campaign->fresh()->recipient_count);
    }

    public function test_cannot_launch_without_recipients_or_twice(): void
    {
        $empty = $this->campaign();
        try {
            $this->svc->launch($empty);
            $this->fail('Exception attendue');
        } catch (InvalidArgumentException) {
            $this->assertSame(CampaignStatus::Draft, $empty->fresh()->status);
        }

        $this->contact('+243811000001');
        $c = $this->campaign();
        $this->svc->launch($c);
        $this->expectException(InvalidArgumentException::class);
        $this->svc->launch($c->fresh());
    }

    public function test_estimate_counts_recipients_cost_and_balance(): void
    {
        $campaign = $this->campaign($this->groupOfThree(), str_repeat('a', 161)); // 2 segments

        $e = $this->svc->estimate($campaign);

        $this->assertSame(2, $e['recipients']);
        $this->assertSame(1, $e['excluded']);
        $this->assertSame(2 * 2 * 5000, $e['cost_units']);
        $this->assertTrue($e['enough']);
    }

    // --- Rejeu et doublons -----------------------------------------------------------

    public function test_replaying_prepare_and_send_jobs_never_duplicates_messages_or_debits(): void
    {
        $campaign = $this->campaign($this->groupOfThree());
        $this->svc->launch($campaign);
        $balance = $this->credits->balance($this->a);

        // Rejeu complet : préparation + tous les jobs d'envoi une seconde (et troisième) fois.
        foreach ([1, 2] as $_) {
            $campaign->update(['status' => CampaignStatus::Preparing->value]); // force une re-préparation
            app()->call([new PrepareCampaign($campaign->id), 'handle']);
            foreach (CampaignMessage::pluck('id') as $id) {
                app()->call([new SendCampaignMessage($id), 'handle']);
            }
        }

        $this->assertSame(2, CampaignMessage::count());
        $this->assertSame(2, SmsMessage::count());
        $this->assertSame($balance, $this->credits->balance($this->a));
        $this->assertSame(2, CreditLedgerEntry::where('type', 'debit')->count());
    }

    public function test_two_workers_racing_on_the_same_recipient_send_once(): void
    {
        $this->contact('+243811000001');
        $campaign = $this->campaign();
        Queue::fake();
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);
        $id = CampaignMessage::value('id');

        // Simule un second worker : le message est déjà pris en charge (status sending) → pas de second envoi.
        app()->call([new SendCampaignMessage($id), 'handle']);
        CampaignMessage::whereKey($id)->update(['processed_at' => null, 'sms_message_id' => null]);
        app()->call([new SendCampaignMessage($id), 'handle']);

        $this->assertSame(1, SmsMessage::count());
        $this->assertSame(1, CreditLedgerEntry::where('type', 'debit')->count());
    }

    public function test_unique_constraint_prevents_duplicate_recipient_rows(): void
    {
        $c = $this->contact('+243811000001');
        $campaign = $this->campaign();
        CampaignMessage::create(['campaign_id' => $campaign->id, 'contact_id' => $c->id, 'phone' => $c->phone]);

        $this->expectException(QueryException::class);
        \DB::table('campaign_messages')->insert([
            'company_id' => $this->a->id, 'campaign_id' => $campaign->id, 'contact_id' => $c->id, 'phone' => $c->phone,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- Consentement revérifié à l'envoi --------------------------------------------

    public function test_opt_out_after_preparation_but_before_sending_blocks_that_message(): void
    {
        $g = $this->groupOfThree();
        $campaign = $this->campaign($g);
        Queue::fake(); // les jobs d'envoi sont mis en file mais pas exécutés
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);
        Queue::assertPushed(SendCampaignMessage::class, 2);

        app(ConsentService::class)->optOut('+243811000001'); // désinscription APRÈS la préparation
        foreach (CampaignMessage::pluck('id') as $id) {
            app()->call([new SendCampaignMessage($id), 'handle']);
        }

        $this->assertSame(1, SmsMessage::where('status', SmsStatus::Sent->value)->count());
        $blocked = SmsMessage::where('status', SmsStatus::Blocked->value)->first();
        $this->assertSame('+243811000001', $blocked->phone);
        $this->assertSame('suppressed', $blocked->blocked_reason);
        $this->assertSame(1, CreditLedgerEntry::where('type', 'debit')->count()); // aucun débit pour le bloqué
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_contact_deleted_between_preparation_and_sending_is_skipped(): void
    {
        $c = $this->contact('+243811000001');
        $campaign = $this->campaign();
        Queue::fake();
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);
        $c->delete();

        app()->call([new SendCampaignMessage(CampaignMessage::value('id')), 'handle']);

        $this->assertSame(0, SmsMessage::count());
        $this->assertSame('contact_deleted', CampaignMessage::first()->note);
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    // --- Pauses ---------------------------------------------------------------------------

    public function test_insufficient_credits_pause_then_resume_sends_remaining_without_duplicates(): void
    {
        $this->credits->adjust($this->a, -(6000000 - 7000), 'drain', 'test'); // reste 7 000 unités = 1 message
        $campaign = $this->campaign($this->groupOfThree());

        $this->svc->launch($campaign);
        $campaign->refresh();
        $this->assertSame(CampaignStatus::Paused, $campaign->status);
        $this->assertSame('insufficient_credits', $campaign->pause_code);
        $this->assertSame(1, SmsMessage::where('status', SmsStatus::Sent->value)->count());

        $this->credits->credit($this->a, 100000, 'topup', 'recharge');
        $this->assertTrue($this->svc->resume($campaign));

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        $this->assertSame(2, SmsMessage::where('status', SmsStatus::Sent->value)->count());
        $this->assertSame(2, CreditLedgerEntry::where('type', 'debit')->count()); // 1 débit par message envoyé, jamais deux
        $this->assertGreaterThanOrEqual(1, SmsMessage::where('blocked_reason', 'insufficient_credits')->count()); // tentative bloquée conservée au journal
        $this->artisan('credits:verify')->assertSuccessful();
    }

    public function test_provider_not_enabled_pauses_campaign_without_writing_messages(): void
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => false]);
        $campaign = $this->campaign($this->groupOfThree());

        $this->svc->launch($campaign);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Paused, $campaign->status);
        $this->assertSame('provider_unavailable', $campaign->pause_code);
        $this->assertSame(0, $live->calls);
        $this->assertSame(0, SmsMessage::count());
        $this->assertSame(0, CampaignMessage::whereNotNull('processed_at')->count()); // aucun destinataire perdu
    }

    public function test_daily_limit_pauses_and_resumes_automatically_next_day(): void
    {
        $live = $this->live();
        config(['sms.sending.probation_daily_limit' => 1]);
        $campaign = $this->campaign($this->groupOfThree());

        $this->svc->launch($campaign);
        $campaign->refresh();
        $this->assertSame('daily_limit', $campaign->pause_code);
        $this->assertSame(1, $live->calls);

        $this->assertSame(0, $this->svc->resumeDailyLimited()); // même jour : pas de reprise automatique

        $this->travel(1)->days();
        config(['sms.sending.probation_daily_limit' => 100]);
        $this->assertSame(1, $this->svc->resumeDailyLimited());

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        $this->assertSame(2, $live->calls);
    }

    public function test_manual_pause_stops_sending_and_resume_continues(): void
    {
        $campaign = $this->campaign($this->groupOfThree());
        Queue::fake();
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);
        $ids = CampaignMessage::pluck('id');

        app()->call([new SendCampaignMessage($ids[0]), 'handle']);
        $this->assertTrue($this->svc->pause($campaign->fresh(), 'manual', 'x'));
        app()->call([new SendCampaignMessage($ids[1]), 'handle']); // en pause : ne fait rien

        $this->assertSame(1, SmsMessage::count());

        Queue::fake(); // la reprise remet en file uniquement le destinataire non traité
        $this->svc->resume($campaign->fresh());
        Queue::assertPushed(SendCampaignMessage::class, 1);
    }

    // --- Annulation -----------------------------------------------------------------------

    public function test_cancel_stops_everything(): void
    {
        $campaign = $this->campaign($this->groupOfThree());
        Queue::fake();
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);

        $this->assertTrue($this->svc->cancel($campaign->fresh()));
        foreach (CampaignMessage::pluck('id') as $id) {
            app()->call([new SendCampaignMessage($id), 'handle']);
        }

        $this->assertSame(0, SmsMessage::count());
        $this->assertSame(CampaignStatus::Cancelled, $campaign->fresh()->status);
        $this->assertFalse($this->svc->resume($campaign->fresh())); // une campagne annulée ne reprend pas
    }

    // --- Programmation ---------------------------------------------------------------------

    public function test_scheduled_campaign_starts_only_when_due(): void
    {
        Queue::fake();
        $campaign = $this->campaign($this->groupOfThree());

        $this->svc->launch($campaign, now()->addHour());
        Queue::assertNothingPushed();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->fresh()->status);
        $this->assertSame(0, $this->svc->dispatchDue());

        $this->travel(61)->minutes();
        $this->assertSame(1, $this->svc->dispatchDue());
        $this->assertSame(0, $this->svc->dispatchDue()); // déjà pris en charge : jamais deux fois
        Queue::assertPushed(PrepareCampaign::class, 1);
        Queue::assertPushedOn(config('sms.queue'), PrepareCampaign::class);
    }

    public function test_only_one_concurrent_actor_can_claim_a_campaign_for_preparation(): void
    {
        $campaign = $this->campaign($this->groupOfThree());
        Campaign::withoutGlobalScopes()->whereKey($campaign->id)->update(['status' => 'scheduled', 'scheduled_at' => now()]);

        $this->assertTrue($this->svc->claimForPreparation($campaign->id));  // le premier obtient la campagne
        $this->assertFalse($this->svc->claimForPreparation($campaign->id)); // le concurrent ne la reçoit pas
        $this->assertSame(CampaignStatus::Preparing, $campaign->fresh()->status);
    }

    public function test_dispatch_due_command_and_schedule_registration(): void
    {
        $this->artisan('campaigns:dispatch-due')->assertSuccessful();
        $this->assertTrue(collect(app(Schedule::class)->events())
            ->contains(fn ($e) => str_contains($e->command, 'campaigns:dispatch-due')));
    }

    public function test_preparation_pushes_one_job_per_recipient_on_the_sms_queue(): void
    {
        Queue::fake();
        $campaign = $this->campaign($this->groupOfThree());
        $this->svc->launch($campaign);
        app()->call([new PrepareCampaign($campaign->id), 'handle']);

        Queue::assertPushedOn('sms', SendCampaignMessage::class);
        Queue::assertPushed(SendCampaignMessage::class, 2);
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    // --- Isolation ---------------------------------------------------------------------------

    public function test_campaign_never_touches_another_companys_contacts_or_credits(): void
    {
        $mine = $this->contact('+243811000001');
        app(CurrentCompany::class)->set($this->b);
        $theirs = $this->contact('+243822000001');
        app(CurrentCompany::class)->set($this->a);
        $campaign = $this->campaign();

        $this->svc->launch($campaign);

        $this->assertSame([$mine->phone], CampaignMessage::pluck('phone')->all());
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->where('company_id', $this->b->id)->count());
        $this->assertSame(6000000, $this->credits->balance($this->b));
        $this->assertNotNull($theirs);
    }

    public function test_execution_context_is_restored_after_a_job_runs(): void
    {
        $this->contact('+243811000001');
        $campaign = $this->campaign();

        $this->svc->launch($campaign); // file synchrone : les jobs s'exécutent dans cet appel

        $this->assertSame($this->a->id, app(CurrentCompany::class)->id()); // le contexte de l'appelant est intact
    }

    public function test_group_of_another_company_targets_nobody(): void
    {
        app(CurrentCompany::class)->set($this->b);
        $foreign = ContactGroup::create(['name' => 'Autre']);
        $c = $this->contact('+243822000001');
        $foreign->addContacts([$c->id]);
        app(CurrentCompany::class)->set($this->a);
        $this->contact('+243811000001');

        $campaign = $this->campaign($foreign);

        $this->assertSame(0, $this->svc->estimate($campaign)['recipients']);
    }

    public function test_status_and_counters_are_not_mass_assignable(): void
    {
        $c = new Campaign(['name' => 'X', 'body' => 'Y', 'status' => 'completed', 'recipient_count' => 999]);
        $c->save();

        $this->assertSame(CampaignStatus::Draft, $c->fresh()->status);
        $this->assertSame(0, $c->fresh()->recipient_count);
    }
}
