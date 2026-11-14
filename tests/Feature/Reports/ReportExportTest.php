<?php

namespace Tests\Feature\Reports;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsReportData;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use BuildsReportData, RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $owner;

    private User $admin;

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
        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->a->users()->attach($this->admin, ['role' => CompanyRole::Admin->value]);
        $this->a->users()->attach($this->member, ['role' => CompanyRole::Member->value]);
    }

    private function url(array $q = []): string
    {
        return route('reports.export', $q + ['from' => '2026-11-10', 'to' => '2026-11-12', 'mode' => 'real']);
    }

    /** @return list<list<string>> lignes du CSV (sans BOM), séparateur ; */
    private function parse(string $csv, string $delimiter = ';'): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM UTF-8 attendu (Excel)');
        $csv = substr($csv, 3);
        $rows = [];
        foreach (array_filter(explode("\r\n", $csv), fn ($l) => $l !== '') as $line) {
            $rows[] = str_getcsv($line, $delimiter, '"', '');
        }

        return $rows;
    }

    // --- Autorisation ---------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())->assertRedirect('/connexion');
    }

    public function test_member_is_refused_but_owner_and_admin_can_export(): void
    {
        $this->msg($this->a);

        $this->actingAs($this->member)->get($this->url())->assertForbidden();
        $this->actingAs($this->owner)->get($this->url())->assertOk();
        $this->actingAs($this->admin)->get($this->url())->assertOk();
    }

    // --- Contenu ----------------------------------------------------------------------

    public function test_csv_content_columns_and_bom(): void
    {
        $campaign = DB::table('campaigns')->insertGetId(['company_id' => $this->a->id, 'name' => 'Promo Noël', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $sentId = $this->msg($this->a, ['phone' => '+243811000001', 'status' => 'sent', 'idempotency_key' => "campaign:{$campaign}:contact:1", 'created_at' => '2026-11-10 08:15:00']);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 08:15:00', $sentId);
        $failedId = $this->msg($this->a, ['phone' => '+243811000002', 'status' => 'failed', 'created_at' => '2026-11-11 09:00:00']);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-11 09:00:00', $failedId);
        $this->ledger($this->a, 'refund', 5000, false, '2026-11-11 09:00:01', $failedId);
        $this->msg($this->a, ['phone' => '+243811000003', 'status' => 'blocked', 'blocked_reason' => 'no_consent', 'segments' => 2, 'created_at' => '2026-11-12 23:59:59']);

        $response = $this->actingAs($this->owner)->get($this->url());
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $rows = $this->parse($response->streamedContent());

        $this->assertSame(['Date', 'Numéro', 'Campagne', 'Statut', 'Motif de blocage', 'Segments', 'Coût en crédits', 'Simulé'], $rows[0]);
        $this->assertCount(4, $rows);
        // Les numéros commencent par « + » : neutralisés comme toute cellule (voir test d'injection).
        $this->assertSame(['2026-11-10 08:15:00', "'+243811000001", 'Promo Noël', 'Envoyé', '', '1', '0,83', 'non'], $rows[1]);
        $this->assertSame(['2026-11-11 09:00:00', "'+243811000002", '', 'Échec', '', '1', '0,00', 'non'], $rows[2], 'débit remboursé : coût net nul');
        $this->assertSame(['2026-11-12 23:59:59', "'+243811000003", '', 'Bloqué', 'Sans consentement', '2', '0,00', 'non'], $rows[3]);
    }

    public function test_comma_delimiter_is_configurable(): void
    {
        config(['reports.csv_delimiter' => ',']);
        $id = $this->msg($this->a, ['created_at' => '2026-11-10 08:15:00']);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 08:15:00', $id);

        $rows = $this->parse($this->actingAs($this->owner)->get($this->url())->streamedContent(), ',');

        $this->assertCount(2, $rows);
        $this->assertCount(8, $rows[1]);
        $this->assertSame('0,83', $rows[1][6], 'la virgule décimale est protégée par des guillemets');
    }

    public function test_export_follows_period_and_mode_and_lists_simulated_flag(): void
    {
        $this->msg($this->a, ['simulated' => false, 'created_at' => '2026-11-10 10:00:00']);
        $this->msg($this->a, ['simulated' => true, 'created_at' => '2026-11-10 10:00:00']);
        $this->msg($this->a, ['simulated' => true, 'created_at' => '2026-11-10 11:00:00']);
        $this->msg($this->a, ['simulated' => true, 'created_at' => '2026-11-13 00:00:00']); // hors période

        $real = $this->parse($this->actingAs($this->owner)->get($this->url(['mode' => 'real']))->streamedContent());
        $sim = $this->parse($this->actingAs($this->owner)->get($this->url(['mode' => 'simulated']))->streamedContent());

        $this->assertSame(['non'], array_values(array_unique(array_column(array_slice($real, 1), 7))));
        $this->assertCount(2, $real);
        $this->assertSame(['oui'], array_values(array_unique(array_column(array_slice($sim, 1), 7))));
        $this->assertCount(3, $sim);
    }

    public function test_streams_in_chunks_without_losing_or_duplicating_rows(): void
    {
        config(['reports.export_chunk' => 2]);
        $phones = [];
        foreach (range(1, 7) as $i) {
            $phones[] = '+24381100000'.$i;
            $this->msg($this->a, ['phone' => '+24381100000'.$i, 'created_at' => '2026-11-10 10:0'.$i.':00']);
        }

        $rows = $this->parse($this->actingAs($this->owner)->get($this->url())->streamedContent());

        $this->assertSame(array_map(fn ($p) => "'".$p, $phones), array_column(array_slice($rows, 1), 1));
    }

    // --- Isolation --------------------------------------------------------------------

    public function test_export_never_contains_another_company_data(): void
    {
        $this->msg($this->a, ['phone' => '+243811000001']);
        $this->msg($this->b, ['phone' => '+243822999999']);
        $this->msg($this->b, ['phone' => '+243822999998', 'simulated' => true]);
        $campB = DB::table('campaigns')->insertGetId(['company_id' => $this->b->id, 'name' => 'Secret de B', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->msg($this->b, ['phone' => '+243822999997', 'idempotency_key' => "campaign:{$campB}:contact:1"]);

        $csv = $this->actingAs($this->owner)->get($this->url())->streamedContent();

        $this->assertStringContainsString('243811000001', $csv);
        $this->assertStringNotContainsString('243822999', $csv);
        $this->assertStringNotContainsString('Secret de B', $csv);
        // Et réciproquement pour l'autre entreprise.
        $csvB = $this->actingAs($this->otherOwner)->get($this->url(['mode' => 'real']))->streamedContent();
        $this->assertStringNotContainsString('243811000001', $csvB);
    }

    public function test_forged_campaign_id_of_another_company_is_404_and_leaks_nothing(): void
    {
        $campB = DB::table('campaigns')->insertGetId(['company_id' => $this->b->id, 'name' => 'Secret de B', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->msg($this->b, ['phone' => '+243822999999', 'idempotency_key' => "campaign:{$campB}:contact:1"]);

        $this->actingAs($this->owner)->get($this->url(['campaign' => $campB]))->assertNotFound();
        $this->actingAs($this->owner)->get($this->url(['campaign' => 999999]))->assertNotFound();
    }

    public function test_platform_admin_has_no_access_to_a_company_they_do_not_belong_to(): void
    {
        $this->msg($this->a, ['phone' => '+243811000001']);
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $own = Company::createFor($platform, 'Plateforme');

        $csv = $this->actingAs($platform)->get($this->url())->streamedContent();

        $this->assertStringNotContainsString('243811000001', $csv);
        $this->assertCount(1, $this->parse($csv), 'seulement l\'en-tête : sa propre entreprise n\'a aucun message');
        $this->assertSame($own->id, app(CurrentCompany::class)->id());
    }

    // --- Campagne ---------------------------------------------------------------------

    public function test_campaign_export_contains_only_that_campaign(): void
    {
        $camp = DB::table('campaigns')->insertGetId(['company_id' => $this->a->id, 'name' => 'Promo', 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->msg($this->a, ['phone' => '+243811000001', 'idempotency_key' => "campaign:{$camp}:contact:1"]);
        $this->msg($this->a, ['phone' => '+243811000002', 'idempotency_key' => "campaign:{$camp}:contact:2:r1"]);
        $this->msg($this->a, ['phone' => '+243811000003', 'idempotency_key' => 'test:xyz']);

        $rows = $this->parse($this->actingAs($this->owner)->get($this->url(['campaign' => $camp]))->streamedContent());

        $this->assertCount(3, $rows);
        $this->assertSame(['Promo', 'Promo'], array_column(array_slice($rows, 1), 2));
    }

    // --- Injection de formules --------------------------------------------------------

    public function test_formula_injection_is_neutralised_in_every_text_cell(): void
    {
        $evil = ['=HYPERLINK("http://evil.example","clic")', '+SUM(1;1)', '-2+3', '@SUM(A1)', "\t=cmd", "\r=cmd"];
        $first = null;
        foreach ($evil as $i => $name) {
            $camp = DB::table('campaigns')->insertGetId(['company_id' => $this->a->id, 'name' => $name, 'body' => 'x', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
            $first ??= $camp;
            $this->msg($this->a, ['phone' => $i === 0 ? '=cmd|\' /C calc\'!A0' : '+24381100000'.$i, 'idempotency_key' => "campaign:{$camp}:contact:1", 'created_at' => '2026-11-10 10:0'.$i.':00']);
        }

        $csv = $this->actingAs($this->owner)->get($this->url())->streamedContent();
        $rows = $this->parse($csv);

        $this->assertCount(7, $rows);
        foreach (array_slice($rows, 1) as $i => $row) {
            foreach ($row as $cell) {
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $cell, 'aucune cellule ne commence par un caractère de formule : '.json_encode($cell));
            }
            $this->assertStringStartsWith("'", $row[2], 'nom de campagne neutralisé');
            $this->assertSame("'".$evil[$i], $row[2]);
        }
        $this->assertSame("'=cmd|' /C calc'!A0", $rows[1][1], 'numéro hostile neutralisé');
    }

    // --- Plafond ----------------------------------------------------------------------

    public function test_over_cap_export_is_refused_with_a_clear_message_and_nothing_is_streamed(): void
    {
        config(['reports.export_max_rows' => 2]);
        foreach (range(1, 3) as $i) {
            $this->msg($this->a, ['created_at' => '2026-11-10 10:0'.$i.':00']);
        }

        $response = $this->actingAs($this->owner)->get($this->url());

        $response->assertRedirect(route('reports.index', ['from' => '2026-11-10', 'to' => '2026-11-12', 'mode' => 'real']));
        $response->assertSessionHas('export_error', fn ($m) => str_contains($m, '3 messages') && str_contains($m, 'plafond de 2 lignes'));
        $this->assertNull($response->headers->get('Content-Disposition'));
    }

    public function test_export_exactly_at_cap_is_allowed(): void
    {
        config(['reports.export_max_rows' => 3]);
        foreach (range(1, 3) as $i) {
            $this->msg($this->a, ['created_at' => '2026-11-10 10:0'.$i.':00']);
        }

        $this->assertCount(4, $this->parse($this->actingAs($this->owner)->get($this->url())->streamedContent()));
    }

    public function test_cap_counts_only_the_active_company_messages(): void
    {
        config(['reports.export_max_rows' => 2]);
        $this->msg($this->a);
        foreach (range(1, 5) as $i) {
            $this->msg($this->b);
        }

        $this->actingAs($this->owner)->get($this->url())->assertOk();
    }

    // --- Validation -------------------------------------------------------------------

    public function test_invalid_filters_are_rejected(): void
    {
        foreach ([['from' => 'abc'], ['to' => '2026-11-09'], ['mode' => 'tout'], ['from' => '2020-01-01']] as $q) {
            $this->actingAs($this->owner)->get($this->url($q))->assertSessionHasErrors();
        }
    }
}
