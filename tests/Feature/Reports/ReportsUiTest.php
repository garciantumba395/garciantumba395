<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\ReportFilters;
use App\Enums\CompanyRole;
use App\Livewire\Campaigns\Show as CampaignShow;
use App\Livewire\Reports\Index;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\BuildsReportData;
use Tests\TestCase;

class ReportsUiTest extends TestCase
{
    use BuildsReportData, RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $owner;

    private User $member;

    private User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();
        $this->a = Company::createFor($this->owner, 'A');
        $this->b = Company::createFor($this->otherOwner, 'B');
        $this->member = User::factory()->create();
        $this->a->users()->attach($this->member, ['role' => CompanyRole::Member->value]);
    }

    private function page(User $u, array $props = [])
    {
        return Livewire::actingAs($u)->test(Index::class)->set('from', '2026-11-01')->set('to', '2026-11-30')->set('mode', 'real')->tap(function ($t) use ($props) {
            foreach ($props as $k => $v) {
                $t->set($k, $v);
            }
        });
    }

    public function test_guest_is_redirected_and_navigation_has_the_link(): void
    {
        $this->get(route('reports.index'))->assertRedirect('/connexion');
        $this->actingAs($this->owner)->get(route('reports.index'))->assertOk()->assertSee(route('reports.index'), false)->assertSee('Rapports');
    }

    public function test_default_period_is_the_last_30_days_in_the_application_timezone(): void
    {
        $p = ReportFilters::defaultPeriod();
        $t = Livewire::actingAs($this->owner)->test(Index::class);

        $t->assertSet('from', $p['from'])->assertSet('to', $p['to'])->assertSet('mode', 'real');
        $this->assertSame(CarbonImmutable::now(config('app.timezone'))->format('Y-m-d'), $p['to']);
    }

    public function test_empty_period_shows_no_data_message_and_no_misleading_zeros(): void
    {
        $t = $this->page($this->owner);

        $t->assertSee('Aucune donnée sur cette période')
            ->assertDontSee('Messages au journal')
            ->assertDontSee('Crédits consommés')
            ->assertDontSee('Taux de délivrance')
            ->assertDontSee('Série journalière');
        $this->assertTrue($t->viewData('summary')->isEmpty());
    }

    public function test_simulated_banner_and_strict_separation_of_blocks(): void
    {
        $this->msg($this->a, ['simulated' => false, 'status' => 'delivered', 'created_at' => '2026-11-10 10:00:00']);
        foreach (range(1, 7) as $i) {
            $this->msg($this->a, ['simulated' => true, 'status' => 'sent', 'created_at' => '2026-11-10 10:00:00']);
        }
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 10:00:00');
        $this->ledger($this->a, 'debit', -35000, true, '2026-11-10 10:00:00');

        $real = $this->page($this->owner, ['mode' => 'real']);
        $real->assertDontSee("Simulation : aucun SMS réel n'a été envoyé", false);
        $this->assertSame(1, $real->viewData('summary')->total);
        $this->assertSame(1, $real->viewData('summary')->delivered);
        $this->assertSame(5000, $real->viewData('summary')->consumedUnits());

        $sim = $this->page($this->owner, ['mode' => 'simulated']);
        $sim->assertSee("Simulation : aucun SMS réel n'a été envoyé", false);
        $this->assertSame(7, $sim->viewData('summary')->total);
        $this->assertSame(0, $sim->viewData('summary')->delivered);
        $this->assertSame(35000, $sim->viewData('summary')->consumedUnits());
        $sim->assertDontSee('Taux de délivrance'); // aucun accusé en simulation : pas de taux trompeur
    }

    public function test_only_simulated_data_shows_empty_real_block_with_hint_not_numbers(): void
    {
        $this->msg($this->a, ['simulated' => true, 'created_at' => '2026-11-10 10:00:00']);

        $t = $this->page($this->owner, ['mode' => 'real']);

        $t->assertSee('Aucune donnée sur cette période')->assertSee("Des données existent dans l'autre onglet", false);
        $this->assertSame(0, $t->viewData('summary')->total);
    }

    public function test_switching_mode_via_tabs_and_rejecting_unknown_mode(): void
    {
        $t = $this->page($this->owner, ['mode' => 'real']);
        $t->call('showMode', 'simulated')->assertSet('mode', 'simulated');
        $t->call('showMode', 'tout')->assertSet('mode', 'real');
        $t->set('mode', 'tout')->assertHasErrors('mode');
    }

    public function test_delivered_depends_on_provider_receipts_notice_and_rates_without_division_by_zero(): void
    {
        $this->msg($this->a, ['status' => 'blocked', 'blocked_reason' => 'no_consent', 'created_at' => '2026-11-10 10:00:00']);

        $t = $this->page($this->owner);

        $t->assertSee('dépend des accusés de réception du fournisseur')
            ->assertSee('non calculable (aucun message envoyé)')
            ->assertSee('non calculable (aucun envoi tenté)')
            ->assertDontSee('NaN')->assertDontSee('INF');
        $this->assertNull($t->viewData('summary')->deliveryRate());
    }

    public function test_totals_blocked_breakdown_and_daily_series_are_displayed(): void
    {
        $this->msg($this->a, ['status' => 'blocked', 'blocked_reason' => 'daily_limit', 'created_at' => '2026-11-10 10:00:00']);
        $this->msg($this->a, ['status' => 'delivered', 'created_at' => '2026-11-10 10:00:00']);
        $this->msg($this->a, ['status' => 'sent', 'created_at' => '2026-11-12 10:00:00']);

        $t = $this->page($this->owner);

        $t->assertSee('Limite journalière atteinte')->assertSee('10/11/2026')->assertSee('12/11/2026')->assertDontSee('11/11/2026');
        $this->assertCount(2, $t->viewData('daily'));
        $this->assertSame('50,0 %', $t->viewData('summary')->deliveryRate());
    }

    public function test_invalid_filters_show_errors_and_no_data(): void
    {
        $this->msg($this->a, ['created_at' => '2026-11-10 10:00:00']);

        $this->page($this->owner, ['to' => '2026-10-01'])->assertHasErrors('to')->assertSee('postérieure ou égale');
        $this->page($this->owner, ['from' => 'pas une date'])->assertHasErrors('from');
        $this->page($this->owner, ['from' => '2025-01-01'])->assertHasErrors('to')->assertSee('ne peut pas dépasser');
        $t = $this->page($this->owner, ['to' => '']);
        $t->assertHasErrors('to');
        $this->assertNull($t->viewData('summary'));
    }

    public function test_forged_campaign_id_of_another_company_shows_nothing(): void
    {
        $campB = DB::table('campaigns')->insertGetId(['company_id' => $this->b->id, 'name' => 'Secret de B', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->msg($this->b, ['idempotency_key' => "campaign:{$campB}:contact:1", 'created_at' => '2026-11-10 10:00:00']);

        $t = $this->page($this->owner, ['campaign' => (string) $campB]);

        $t->assertHasErrors('campaign')->assertSee('Campagne introuvable')->assertDontSee('Secret de B');
        $this->assertNull($t->viewData('summary'));
        $this->assertNull($t->viewData('exportUrl'));
    }

    public function test_other_company_messages_are_never_counted(): void
    {
        $this->msg($this->a, ['created_at' => '2026-11-10 10:00:00']);
        foreach (range(1, 4) as $i) {
            $this->msg($this->b, ['created_at' => '2026-11-10 10:00:00']);
        }
        $this->ledger($this->b, 'debit', -777000, false, '2026-11-10 10:00:00');

        $t = $this->page($this->owner);

        $this->assertSame(1, $t->viewData('summary')->total);
        $this->assertSame(0, $t->viewData('summary')->consumedUnits());
        $this->assertSame(4, $this->page($this->otherOwner)->viewData('summary')->total);
    }

    public function test_campaign_report_is_filtered_and_titled(): void
    {
        $camp = DB::table('campaigns')->insertGetId(['company_id' => $this->a->id, 'name' => 'Promo été', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->msg($this->a, ['idempotency_key' => "campaign:{$camp}:contact:1", 'created_at' => '2026-11-10 10:00:00']);
        $this->msg($this->a, ['idempotency_key' => 'test:1', 'created_at' => '2026-11-10 10:00:00']);

        $t = $this->page($this->owner, ['campaign' => (string) $camp]);

        $this->assertSame(1, $t->viewData('summary')->total);
        $t->assertSee('Rapport de la campagne')->assertSee('Promo été');
    }

    public function test_export_button_only_for_owner_and_admin_members_see_aggregates(): void
    {
        $this->msg($this->a, ['created_at' => '2026-11-10 10:00:00']);

        $owner = $this->page($this->owner);
        $owner->assertSee('Télécharger le CSV');
        $this->assertStringContainsString('/rapports/export?', $owner->viewData('exportUrl'));

        $member = $this->page($this->member);
        $member->assertDontSee('Télécharger le CSV')->assertSee('réservé aux propriétaires et administrateurs');
        $this->assertNull($member->viewData('exportUrl'));
        $this->assertSame(1, $member->viewData('summary')->total, 'le membre voit les agrégats');
    }

    public function test_export_error_message_is_displayed_after_a_refused_export(): void
    {
        $this->actingAs($this->owner)->withSession(['export_error' => 'Export refusé : trop de lignes.'])
            ->get(route('reports.index'))->assertSee('Export refusé : trop de lignes.');
    }

    public function test_campaign_page_links_to_the_filtered_report(): void
    {
        $camp = DB::table('campaigns')->insertGetId(['company_id' => $this->a->id, 'name' => 'Promo', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);

        $t = Livewire::actingAs($this->owner)->test(CampaignShow::class, ['campaign' => $camp]);

        $t->assertSee('Voir le rapport détaillé de cette campagne')->assertSeeHtml('campaign='.$camp);
    }
}
