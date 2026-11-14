<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\AccountService;
use App\Domain\Audit\AuditLogger;
use App\Domain\Campaigns\CampaignService;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Livewire\Admin\Companies;
use App\Livewire\Admin\CompanyShow;
use App\Livewire\Admin\Dashboard;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['Zéphyrin Confidentiel', '+243819998877', '0819998877', '819998877', 'MOTSECRET-CORPS', 'PREUVE-SECRETE', 'CAMPAGNE-SECRETE', 'zephyrin@example.test'];

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

    private function seedBusinessData(): void
    {
        app(CurrentCompany::class)->runAs($this->co, function () {
            $k = Contact::create(['name' => 'Zéphyrin Confidentiel', 'phone' => '+243819998877', 'email' => 'zephyrin@example.test']);
            app(ConsentService::class)->record($k, 'paper', 'PREUVE-SECRETE', now()->subDay());
            SmsMessage::create(['phone' => '+243819998877', 'body' => 'MOTSECRET-CORPS', 'status' => 'sent', 'simulated' => false, 'idempotency_key' => 'k1']);
            SmsMessage::create(['phone' => '+243819998877', 'body' => 'MOTSECRET-CORPS', 'status' => 'sent', 'simulated' => true, 'idempotency_key' => 'k2']);
            app(ConsentService::class)->optOut('+243819998877');
            $c = new Campaign(['name' => 'CAMPAGNE-SECRETE', 'body' => 'MOTSECRET-CORPS']);
            $c->created_by = $this->owner->id;
            $c->save();
        });
        app(CreditService::class)->credit($this->co, 600000, 'seed', 'Paiement ref. 42', $this->admin->id);
    }

    public function test_dashboard_shows_real_figures_with_real_and_simulated_messages_separated(): void
    {
        $other = Company::createFor(User::factory()->create(), 'Autre SARL');
        $other->forceFill(['is_active' => false])->save();
        $this->seedBusinessData();
        app(CreditService::class)->credit($other, 120000, 'seed2');
        app(CurrentCompany::class)->runAs($this->co, function () {
            $c = new Campaign(['name' => 'En cours', 'body' => 'x']);
            $c->created_by = $this->owner->id;
            $c->save();
            Campaign::withoutGlobalScopes()->whereKey($c->id)->update(['status' => 'sending']);
        });
        DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'redis', 'queue' => 'sms', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        DB::table('failed_jobs')->insert(['uuid' => 'u2', 'connection' => 'redis', 'queue' => 'sms', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

        $page = Livewire::actingAs($this->admin)->test(Dashboard::class);

        $this->assertSame(2, $page->viewData('companies'));
        $this->assertSame(1, $page->viewData('companiesActive'));
        $this->assertSame(3, $page->viewData('users'));
        $this->assertSame(1, $page->viewData('messagesReal'));
        $this->assertSame(1, $page->viewData('messagesSimulated'));
        $this->assertSame(720000, $page->viewData('creditUnits'));
        $this->assertSame(1, $page->viewData('campaignsActive')); // le brouillon n'est pas actif
        $this->assertSame(2, $page->viewData('failedJobs'));
        $page->assertSee('Messages réels')->assertSee('Messages simulés');
    }

    public function test_dashboard_of_an_empty_platform_shows_zeros_not_invented_figures(): void
    {
        DB::table('companies')->delete();
        $page = Livewire::actingAs($this->admin)->test(Dashboard::class);
        $this->assertSame(0, $page->viewData('companies'));
        $this->assertSame(0, $page->viewData('messagesReal'));
        $this->assertSame(0, $page->viewData('messagesSimulated'));
        $this->assertSame(0, $page->viewData('creditUnits'));
        $this->assertSame(0, $page->viewData('failedJobs'));
    }

    public function test_no_admin_page_exposes_contacts_numbers_message_text_or_consents(): void
    {
        $this->seedBusinessData();
        app(AuditLogger::class)->record('suppression.lifted', $this->co, null, ['reason' => 'Demande du 0819998877']);

        $urls = ['/admin', '/admin/entreprises', '/admin/entreprises/'.$this->co->id, '/admin/utilisateurs', '/admin/audit', '/admin/tarifs'];
        foreach ($urls as $url) {
            $body = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, $body, "$url expose « $secret »");
            }
        }

        // Page « entreprises » avec l'entreprise sélectionnée (grand livre, formulaires).
        $html = Livewire::actingAs($this->admin)->test(Companies::class)->set('companyId', $this->co->id)->html();
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringNotContainsString('819998877', json_encode(AuditLog::all()->toArray()));
    }

    public function test_platform_admin_cannot_read_company_data_through_company_scoped_models(): void
    {
        $this->seedBusinessData();
        // Sans entreprise active (l'administrateur n'est membre d'aucune), les modèles métier ne renvoient rien (fail-closed).
        $this->actingAs($this->admin);
        $this->assertSame(0, Contact::count());
        $this->assertSame(0, SmsMessage::count());
        $this->assertSame(0, Campaign::count());
    }

    public function test_company_sheet_shows_members_balance_and_status_only(): void
    {
        $this->seedBusinessData();
        $this->actingAs($this->admin)->get('/admin/entreprises/'.$this->co->id)
            ->assertOk()->assertSee('Cliente SARL')->assertSee($this->owner->email)->assertSee('Démarrage')->assertSee('100,00');
    }

    public function test_companies_list_search_and_status_filter(): void
    {
        $b = Company::createFor(User::factory()->create(), 'Banque Tuma');
        $b->forceFill(['sending_status' => 'suspended'])->save();

        $page = Livewire::actingAs($this->admin)->test(Companies::class);
        $this->assertSame(['Banque Tuma', 'Cliente SARL'], $page->viewData('companies')->pluck('name')->all());
        $page->set('search', 'cLiEnTe');
        $this->assertSame(['Cliente SARL'], $page->viewData('companies')->pluck('name')->all());
        $page->set('search', '')->set('statusFilter', 'suspended');
        $this->assertSame(['Banque Tuma'], $page->viewData('companies')->pluck('name')->all());
        $page->set('statusFilter', 'probation');
        $this->assertSame(['Cliente SARL'], $page->viewData('companies')->pluck('name')->all());
        $page->set('statusFilter', '')->set('search', '%');
        $this->assertCount(2, $page->viewData('companies')); // « % » ne sert pas de joker
    }

    public function test_disabled_company_cannot_be_used_by_its_members(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(CompanyShow::class, ['company' => $this->co->id])->set('reason', 'Test')->call('disable');
        auth()->logout();

        $this->actingAs($this->owner)->get('/contacts')->assertRedirect('/entreprises/nouvelle');
    }

    public function test_disabling_a_company_pauses_running_campaigns_and_blocks_scheduled_ones(): void
    {
        [$sending, $scheduled] = app(CurrentCompany::class)->runAs($this->co, function () {
            $ids = [];
            foreach (['sending', 'scheduled'] as $status) {
                $c = new Campaign(['name' => $status, 'body' => 'x']);
                $c->created_by = $this->owner->id;
                $c->save();
                Campaign::withoutGlobalScopes()->whereKey($c->id)->update(['status' => $status, 'scheduled_at' => now()->subMinute()]);
                $ids[] = $c->id;
            }

            return $ids;
        });

        $this->actingAs($this->admin);
        app(AccountService::class)->disableCompany($this->co, 'Fraude suspectée');

        $this->assertSame('paused', Campaign::withoutGlobalScopes()->find($sending)->status->value);
        $this->assertSame('company_disabled', Campaign::withoutGlobalScopes()->find($sending)->pause_code);
        $this->assertSame(0, app(CampaignService::class)->dispatchDue());
        $this->assertSame('scheduled', Campaign::withoutGlobalScopes()->find($scheduled)->status->value);
    }
}
