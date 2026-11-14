<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
use App\Enums\CompanyRole;
use App\Livewire\Admin\Companies;
use App\Livewire\Admin\CompanyShow;
use App\Livewire\Admin\Rates;
use App\Livewire\Admin\Users;
use App\Livewire\Campaigns\Show as CampaignShow;
use App\Livewire\Companies\Settings;
use App\Livewire\Suppressions\Index as SuppressionIndex;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SmsRate;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $co;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->owner = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Cliente SARL');
    }

    private function last(string $action): AuditLog
    {
        $log = AuditLog::where('action', $action)->latest('id')->first();
        $this->assertNotNull($log, "entrée d'audit $action absente");

        return $log;
    }

    // ---- ajout seul ----

    public function test_audit_log_is_append_only(): void
    {
        $log = app(AuditLogger::class)->record('test.action', $this->co);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'test.other']);
    }

    public function test_audit_log_entries_cannot_be_deleted(): void
    {
        $log = app(AuditLogger::class)->record('test.action', $this->co);

        try {
            $log->delete();
            $this->fail('La suppression aurait dû être refusée.');
        } catch (LogicException) {
            $this->assertSame(1, AuditLog::count());
        }
    }

    public function test_actor_ip_and_truncated_user_agent_are_recorded(): void
    {
        $this->actingAs($this->admin);
        $this->assertSame($this->admin->id, app(AuditLogger::class)->record('test.action')->actor_id);

        $this->app['request']->headers->set('User-Agent', str_repeat('B', 600));
        $log = app(AuditLogger::class)->record('test.action2');
        $this->assertLessThanOrEqual(255, strlen($log->user_agent));
        $this->assertNotNull($log->ip_address);
    }

    public function test_system_entries_have_no_actor(): void
    {
        $this->actingAs($this->admin);
        $this->assertNull(app(AuditLogger::class)->recordSystem('test.system', $this->co)->actor_id);
    }

    // ---- aucune donnée sensible ----

    public function test_forbidden_detail_keys_are_refused(): void
    {
        foreach (['password', 'api_token', 'secret_key', 'message_body', 'phone', 'phone_number', 'email', 'text_content', 'Authorization'] as $key) {
            try {
                app(AuditLogger::class)->record('test.action', null, null, [$key => 'x']);
                $this->fail("La clé $key aurait dû être refusée.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, AuditLog::count());
    }

    public function test_phone_numbers_and_emails_in_free_text_are_masked(): void
    {
        $log = app(AuditLogger::class)->record('test.action', null, null, [
            'reason' => 'Demande de +243 81 234 5678 et 0812345678 par jean@example.test',
            'nested' => ['note' => 'tel +243812345678'],
        ]);
        $raw = json_encode($log->fresh()->details, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('812345678', $raw);
        $this->assertStringNotContainsString('81 234', $raw);
        $this->assertStringNotContainsString('jean@example.test', $raw);
        $this->assertStringContainsString(AuditLogger::MASK, $raw);
    }

    public function test_invalid_action_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AuditLogger::class)->record('Not A Key');
    }

    // ---- instrumentation ----

    public function test_credit_add_and_removal_are_audited_with_the_acting_admin(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Companies::class)
            ->set('companyId', $this->co->id)->set('amount', '100')->set('reason', 'Paiement ref. 42')->call('submit');
        $added = $this->last('credits.added');
        $this->assertSame($this->admin->id, $added->actor_id);
        $this->assertSame($this->co->id, $added->company_id);
        $this->assertSame('CreditLedgerEntry', $added->subject_type);
        $this->assertSame(600000, $added->details['units']);

        $page->set('operation', 'retrait')->set('amount', '10')->set('reason', 'Correction')->call('submit');
        $removed = $this->last('credits.removed');
        $this->assertSame($this->admin->id, $removed->actor_id);
        $this->assertSame(60000, $removed->details['units']);
        $this->assertSame(2, AuditLog::whereIn('action', ['credits.added', 'credits.removed'])->count());
    }

    public function test_failed_credit_operation_is_not_audited(): void
    {
        Livewire::actingAs($this->admin)->test(Companies::class)
            ->set('companyId', $this->co->id)->set('operation', 'retrait')->set('amount', '10')->set('reason', 'Trop')->call('submit')
            ->assertHasErrors('amount');
        $this->assertSame(0, AuditLog::count());
    }

    public function test_rate_creation_and_toggle_are_audited(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Rates::class)->set('country', 'CD')->set('smsPer100', '120')->call('add');
        $created = $this->last('rates.created');
        $this->assertSame($this->admin->id, $created->actor_id);
        $this->assertNull($created->company_id);

        $rate = SmsRate::firstOrFail();
        $page->call('toggle', $rate->id);
        $this->assertSame($this->admin->id, $this->last('rates.deactivated')->actor_id);
        $page->call('toggle', $rate->id);
        $this->assertNotNull($this->last('rates.activated'));
    }

    public function test_manual_sending_status_change_is_audited_with_actor_and_reason(): void
    {
        Livewire::actingAs($this->admin)->test(Companies::class)
            ->set('companyId', $this->co->id)->set('sendingStatus', 'suspended')->set('sendingReason', 'Plaintes reçues')->set('dailyLimit', '50')->call('updateSending');

        $log = $this->last('sending.status_changed');
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->co->id, $log->company_id);
        $this->assertSame(['from' => 'probation', 'to' => 'suspended', 'reason' => 'Plaintes reçues'], $log->details);
        $this->assertSame(50, $this->last('sending.limit_changed')->details['to']);
    }

    public function test_automatic_sending_status_transition_is_audited_as_system(): void
    {
        // Transition automatique : acteur « système », même si un utilisateur est connecté pendant le job synchrone.
        $this->actingAs($this->admin);
        config(['sms.sending.min_sample' => 1]);
        $this->forceProbationSample();

        app(SendingPolicy::class)->evaluate($this->co->id);

        $log = $this->last('sending.status_auto_changed');
        $this->assertNull($log->actor_id);
        $this->assertSame($this->co->id, $log->company_id);
        $this->assertSame('probation', $log->details['from']);
        $this->assertContains($log->details['to'], ['validated', 'suspended']);
        $this->assertNotSame('', $log->details['reason']);
    }

    private function forceProbationSample(): void
    {
        \DB::table('sms_messages')->insert([
            'company_id' => $this->co->id, 'phone' => '+243810000001', 'body' => 'x', 'status' => 'sent', 'simulated' => false,
            'idempotency_key' => 'k1', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_manual_status_change_without_logged_user_is_not_recorded_as_automatic(): void
    {
        app(SendingPolicy::class)->setStatus($this->co, SendingStatus::Validated, 'Validation par la console');
        $this->assertNull(AuditLog::where('action', 'sending.status_auto_changed')->first());
        $this->assertNotNull($this->last('sending.status_changed'));
    }

    public function test_suppression_lift_is_audited_without_number_or_reason(): void
    {
        $s = app(CurrentCompany::class)->runAs($this->co, fn () => app(ConsentService::class)->optOut('+243812345678'));

        Livewire::actingAs($this->owner)->test(SuppressionIndex::class)
            ->set("liftReasons.{$s->id}", 'Demande écrite du client 0812345678')->call('lift', $s->id);

        $log = $this->last('suppression.lifted');
        $this->assertSame($this->owner->id, $log->actor_id);
        $this->assertSame($this->co->id, $log->company_id);
        $this->assertSame($s->id, $log->subject_id);
        $this->assertStringNotContainsString('812345678', json_encode($log->toArray()));
    }

    private function campaign(string $status = 'draft'): Campaign
    {
        return app(CurrentCompany::class)->runAs($this->co, function () use ($status) {
            $c = new Campaign(['name' => 'Promo', 'body' => 'Bonjour']);
            $c->created_by = $this->owner->id;
            $c->save();
            Campaign::withoutGlobalScopes()->whereKey($c->id)->update(['status' => $status]);

            return $c->fresh();
        });
    }

    public function test_campaign_pause_resume_cancel_are_audited(): void
    {
        $campaign = $this->campaign(CampaignStatus::Sending->value);
        $page = Livewire::actingAs($this->owner)->test(CampaignShow::class, ['campaign' => $campaign->id]);

        $page->call('pause');
        $this->assertSame($this->owner->id, $this->last('campaign.paused')->actor_id);
        $this->assertSame(['code' => 'manual'], $this->last('campaign.paused')->details);
        $page->call('resume');
        $this->assertSame($this->owner->id, $this->last('campaign.resumed')->actor_id);
        $page->call('cancel');
        $cancelled = $this->last('campaign.cancelled');
        $this->assertSame($this->owner->id, $cancelled->actor_id);
        $this->assertSame($campaign->id, $cancelled->subject_id);
        $this->assertSame($this->co->id, $cancelled->company_id);

        // Une action sans effet (déjà annulée) n'ajoute rien.
        $before = AuditLog::count();
        $page->call('cancel');
        $this->assertSame($before, AuditLog::count());
    }

    public function test_campaign_launch_is_audited(): void
    {
        app(CurrentCompany::class)->runAs($this->co, function () {
            $k = Contact::create(['phone' => '+243810000002']);
            app(ConsentService::class)->record($k, 'paper', 'x', now()->subDay());
        });
        app(CreditService::class)->credit($this->co, 6000000, 'seed');
        $campaign = $this->campaign();

        Livewire::actingAs($this->owner)->test(CampaignShow::class, ['campaign' => $campaign->id])->call('launch');

        $log = $this->last('campaign.launched');
        $this->assertSame($this->owner->id, $log->actor_id);
        $this->assertSame($campaign->id, $log->subject_id);
    }

    public function test_automatic_campaign_pause_is_audited_as_system(): void
    {
        $this->actingAs($this->admin);
        $campaign = $this->campaign(CampaignStatus::Sending->value);

        app(CampaignService::class)->pause($campaign, 'insufficient_credits', 'Crédits insuffisants');

        $log = $this->last('campaign.paused');
        $this->assertNull($log->actor_id);
        $this->assertSame(['code' => 'insufficient_credits'], $log->details);
    }

    public function test_company_settings_change_is_audited_by_the_actor(): void
    {
        Livewire::actingAs($this->owner)->test(Settings::class)->set('name', 'Nouveau Nom')->set('country', 'CD')->call('save');

        $log = $this->last('company.updated');
        $this->assertSame($this->owner->id, $log->actor_id);
        $this->assertSame(['name' => ['from' => 'Cliente SARL', 'to' => 'Nouveau Nom']], $log->details);

        $before = AuditLog::count();
        Livewire::actingAs($this->owner)->test(Settings::class)->call('save'); // rien de changé : pas d'entrée
        $this->assertSame($before, AuditLog::count());
    }

    public function test_company_disable_and_enable_require_a_reason_and_are_audited(): void
    {
        $page = Livewire::actingAs($this->admin)->test(CompanyShow::class, ['company' => $this->co->id]);
        $page->call('disable')->assertHasErrors('reason');
        $this->assertTrue($this->co->fresh()->is_active);

        $page->set('reason', 'Fraude suspectée')->call('disable')->assertHasNoErrors();
        $this->assertFalse($this->co->fresh()->is_active);
        $log = $this->last('company.disabled');
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->co->id, $log->company_id);
        $this->assertSame('Fraude suspectée', $log->details['reason']);

        $page->set('reason', 'Vérification terminée')->call('enable');
        $this->assertTrue($this->co->fresh()->is_active);
        $this->assertSame($this->admin->id, $this->last('company.enabled')->actor_id);
    }

    public function test_user_disable_and_enable_are_audited(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Users::class);
        $page->set("reasons.{$this->owner->id}", 'Compte compromis')->call('disable', $this->owner->id);
        $log = $this->last('user.disabled');
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->owner->id, $log->subject_id);

        $page->set("reasons.{$this->owner->id}", 'Résolu')->call('enable', $this->owner->id);
        $this->assertSame($this->admin->id, $this->last('user.enabled')->actor_id);
    }

    public function test_member_role_cannot_forge_company_settings_audit(): void
    {
        $member = User::factory()->create();
        $this->co->users()->attach($member, ['role' => CompanyRole::Member->value]);
        Livewire::actingAs($member)->test(Settings::class)->assertForbidden();
        $this->assertSame(0, AuditLog::count());
    }
}
