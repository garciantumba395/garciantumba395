<?php

namespace Tests\Feature\Reports;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportService;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsReportData;
use Tests\Support\CountingLiveProvider;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use BuildsReportData, RefreshDatabase;

    private Company $a;

    private Company $b;

    private ReportService $svc;

    private string $originalTz;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->originalTz = date_default_timezone_get();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        $this->svc = app(ReportService::class);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTz);
        parent::tearDown();
    }

    private function f(string $from, string $to, string $mode = 'real', ?int $campaign = null): ReportFilters
    {
        return new ReportFilters($from, $to, $campaign, $mode);
    }

    // --- Totaux ---------------------------------------------------------------------

    public function test_totals_count_each_status_and_blocked_reasons(): void
    {
        foreach (['sent', 'sent', 'delivered', 'failed', 'unknown', 'pending', 'sending'] as $s) {
            $this->msg($this->a, ['status' => $s]);
        }
        foreach (['no_consent', 'no_consent', 'suppressed', 'insufficient_credits', 'daily_limit', 'suspended', 'mystery', null] as $r) {
            $this->msg($this->a, ['status' => 'blocked', 'blocked_reason' => $r]);
        }

        $s = $this->svc->summary($this->a->id, $this->f('2026-11-01', '2026-11-30'));

        $this->assertSame(15, $s->total);
        $this->assertSame(3, $s->sent, 'envoyés = sent + delivered');
        $this->assertSame(1, $s->delivered);
        $this->assertSame(1, $s->failed);
        $this->assertSame(1, $s->unknown);
        $this->assertSame(2, $s->inProgress);
        $this->assertSame(8, $s->blocked);
        $this->assertSame(['no_consent' => 2, 'suppressed' => 1, 'insufficient_credits' => 1, 'daily_limit' => 1, 'suspended' => 1, 'other' => 2], $s->blockedByReason);
        $this->assertSame($s->blocked, array_sum($s->blockedByReason), 'la ventilation par motif couvre tous les bloqués');
        $this->assertSame($s->total, $s->sent + $s->failed + $s->unknown + $s->inProgress + $s->blocked, 'tous les statuts sont comptés une fois');
    }

    public function test_consumed_credits_come_from_the_ledger_debits_minus_refunds(): void
    {
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 10:00:00');
        $this->ledger($this->a, 'debit', -10000, false, '2026-11-10 11:00:00');
        $this->ledger($this->a, 'refund', 5000, false, '2026-11-10 11:00:01');
        $this->ledger($this->a, 'credit', 600000, false, '2026-11-10 09:00:00');      // ajout : pas une consommation
        $this->ledger($this->a, 'adjustment', -1000, false, '2026-11-10 09:00:00');    // correction : pas une consommation

        $s = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10'));

        $this->assertSame(15000, $s->debitedUnits);
        $this->assertSame(5000, $s->refundedUnits);
        $this->assertSame(10000, $s->consumedUnits());
        $this->assertFalse($s->isEmpty(), 'des écritures de grand livre suffisent à ne pas afficher « aucune donnée »');
    }

    // --- Séparation simulé / réel ----------------------------------------------------

    public function test_simulated_and_real_never_mix(): void
    {
        $this->msg($this->a, ['simulated' => false, 'status' => 'delivered']);
        $this->msg($this->a, ['simulated' => false, 'status' => 'sent']);
        $this->msg($this->a, ['simulated' => true, 'status' => 'sent']);
        $this->msg($this->a, ['simulated' => true, 'status' => 'failed']);
        $this->msg($this->a, ['simulated' => true, 'status' => 'blocked', 'blocked_reason' => 'no_consent']);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 10:00:00');
        $this->ledger($this->a, 'debit', -7000, true, '2026-11-10 10:00:00');

        $real = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real'));
        $sim = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'simulated'));

        $this->assertSame([2, 2, 1, 0, 0, 5000], [$real->total, $real->sent, $real->delivered, $real->failed, $real->blocked, $real->consumedUnits()]);
        $this->assertSame([3, 1, 0, 1, 1, 7000], [$sim->total, $sim->sent, $sim->delivered, $sim->failed, $sim->blocked, $sim->consumedUnits()]);

        // La série journalière respecte aussi la séparation.
        $this->assertSame(2, $this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real'))[0]['total']);
        $this->assertSame(3, $this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-10', 'simulated'))[0]['total']);
    }

    public function test_real_block_is_empty_when_only_simulated_data_exists_and_vice_versa(): void
    {
        $this->msg($this->a, ['simulated' => true]);
        $this->ledger($this->a, 'debit', -5000, true, '2026-11-10 10:00:00');

        $this->assertTrue($this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real'))->isEmpty());
        $this->assertFalse($this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'simulated'))->isEmpty());
        $this->assertSame([], $this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real')));
    }

    public function test_real_dispatch_flow_is_never_counted_as_simulated_and_vice_versa(): void
    {
        // Flux complet (répartiteur, grand livre) : 2 envois simulés puis 3 envois réels (fournisseur de test sans réseau).
        $owner = User::factory()->create();
        $co = Company::createFor($owner, 'Flux');
        app(CreditService::class)->credit($co, 60000000, 'seed');
        app(CurrentCompany::class)->set($co->fresh());
        $dispatcher = app(SmsDispatcher::class);

        $contacts = collect(range(1, 5))->map(function ($i) {
            $c = Contact::create(['phone' => '+2438110000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());

            return $c;
        });
        foreach ($contacts->take(2) as $i => $c) {
            $dispatcher->dispatch($c, 'Simulé', 'sim:'.$i);
        }
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true, 'sms.sending.probation_daily_limit' => 1000]);
        foreach ($contacts->slice(2) as $i => $c) {
            $dispatcher->dispatch($c, 'Réel', 'real:'.$i);
        }

        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $real = $this->svc->summary($co->id, $this->f($today, $today, 'real'));
        $sim = $this->svc->summary($co->id, $this->f($today, $today, 'simulated'));

        $this->assertSame(3, $real->total);
        $this->assertSame(3, $real->sent);
        $this->assertSame(3 * 5000, $real->consumedUnits());
        $this->assertSame(2, $sim->total);
        $this->assertSame(2, $sim->sent);
        $this->assertSame(2 * 5000, $sim->consumedUnits());
        $this->assertSame(3, $live->calls, 'seuls les envois réels ont appelé le fournisseur');
    }

    // --- Cohérence avec le grand livre et credits:verify -----------------------------

    public function test_totals_match_the_ledger_balance_and_credits_verify(): void
    {
        $co = Company::createFor(User::factory()->create(), 'Cohérence');
        $credits = app(CreditService::class);
        $credits->credit($co, 6000000, 'seed');
        app(CurrentCompany::class)->set($co->fresh());
        $dispatcher = app(SmsDispatcher::class);
        $rejected = '+243811000002';

        $live = new CountingLiveProvider;
        $live->rejectNumbers = [$rejected];
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true, 'sms.sending.probation_daily_limit' => 1000]);

        foreach (['+243811000001', $rejected, '+243811000003'] as $i => $phone) {
            $c = Contact::create(['phone' => $phone]);
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());
            $dispatcher->dispatch($c, 'Message', 'k:'.$i);
        }
        $noConsent = Contact::create(['phone' => '+243811000004']);
        $dispatcher->dispatch($noConsent, 'Message', 'k:blocked');

        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $s = $this->svc->summary($co->id, $this->f($today, $today, 'real'));

        $this->assertSame(2, $s->sent);
        $this->assertSame(1, $s->failed);
        $this->assertSame(1, $s->blocked);
        $this->assertSame(1, $s->blockedByReason['no_consent']);
        $this->assertSame(3 * 5000, $s->debitedUnits);
        $this->assertSame(1 * 5000, $s->refundedUnits);
        $this->assertSame(2 * 5000, $s->consumedUnits());
        // Le solde de l'entreprise = crédits ajoutés − crédits consommés rapportés.
        $this->assertSame(6000000 - $s->consumedUnits(), $credits->balance($co));
        $this->assertSame(0, Artisan::call('credits:verify'));
    }

    // --- Période, bornes et fuseaux ---------------------------------------------------

    public function test_period_bounds_are_inclusive_of_the_whole_end_day_and_exclude_neighbours(): void
    {
        $this->msg($this->a, ['created_at' => '2026-11-09 23:59:59']); // veille : exclu
        $this->msg($this->a, ['created_at' => '2026-11-10 00:00:00']); // début : inclus
        $this->msg($this->a, ['created_at' => '2026-11-12 23:59:59']); // dernier instant de la fin : inclus
        $this->msg($this->a, ['created_at' => '2026-11-13 00:00:00']); // lendemain de la fin : exclu

        $s = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-12'));
        $this->assertSame(2, $s->total);

        $daily = $this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-12'));
        $this->assertSame(['2026-11-10', '2026-11-12'], array_column($daily, 'day'));
    }

    public function test_single_day_period_boundary_day(): void
    {
        $this->msg($this->a, ['created_at' => '2026-11-10 00:00:00']);
        $this->msg($this->a, ['created_at' => '2026-11-10 23:59:59']);
        $this->msg($this->a, ['created_at' => '2026-11-11 00:00:00']);

        $this->assertSame(2, $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10'))->total);
        $this->assertSame(1, $this->svc->summary($this->a->id, $this->f('2026-11-11', '2026-11-11'))->total);
    }

    public function test_daily_series_groups_by_day_with_credits_per_day(): void
    {
        $a1 = $this->msg($this->a, ['created_at' => '2026-11-10 08:00:00', 'status' => 'delivered']);
        $this->msg($this->a, ['created_at' => '2026-11-10 20:00:00', 'status' => 'failed']);
        $this->msg($this->a, ['created_at' => '2026-11-12 09:00:00', 'status' => 'blocked', 'blocked_reason' => 'suppressed']);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 08:00:00', $a1);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 20:00:00');
        $this->ledger($this->a, 'refund', 5000, false, '2026-11-10 20:00:01');

        $daily = $this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-12'));

        $this->assertCount(2, $daily);
        $this->assertSame(['day' => '2026-11-10', 'total' => 2, 'sent' => 1, 'delivered' => 1, 'failed' => 1, 'unknown' => 0, 'blocked' => 0, 'in_progress' => 0, 'consumed_units' => 5000], $daily[0]);
        $this->assertSame(1, $daily[1]['blocked']);
        $this->assertSame(0, $daily[1]['consumed_units']);
        // La somme des jours égale le total de la période.
        $s = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-12'));
        $this->assertSame($s->total, array_sum(array_column($daily, 'total')));
        $this->assertSame($s->consumedUnits(), array_sum(array_column($daily, 'consumed_units')));
    }

    public static function timezones(): array
    {
        return [['UTC'], ['Africa/Kinshasa'], ['Pacific/Auckland']];
    }

    /**
     * Un message créé à un instant précis est rangé dans le JOUR CIVIL du fuseau de l'application (pas de l'UTC),
     * et la période par défaut « aujourd'hui » est celle de ce fuseau.
     */
    #[DataProvider('timezones')]
    public function test_message_day_follows_the_application_timezone(string $tz): void
    {
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        foreach (['2026-11-14 23:30:00', '2026-11-14 00:30:00', '2026-11-14 12:00:00'] as $utcInstant) {
            $this->travelTo(CarbonImmutable::parse($utcInstant, 'UTC'));
            $local = CarbonImmutable::parse($utcInstant, 'UTC')->setTimezone($tz);

            $co = Company::createFor(User::factory()->create(), 'TZ '.$tz.' '.$utcInstant);
            app(CreditService::class)->credit($co, 6000000, 'seed');
            app(CurrentCompany::class)->set($co->fresh());
            $c = Contact::create(['phone' => '+243811999999']);
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());
            app(SmsDispatcher::class)->dispatch($c, 'TZ', 'tz:1');

            $day = $local->format('Y-m-d');
            $this->assertSame($day, ReportFilters::defaultPeriod()['to'], "« aujourd'hui » suit le fuseau {$tz}");
            $this->assertSame(1, $this->svc->summary($co->id, $this->f($day, $day, 'simulated'))->total);
            $this->assertSame([$day], array_column($this->svc->daily($co->id, $this->f($day, $day, 'simulated')), 'day'));
            $this->assertSame(0, $this->svc->summary($co->id, $this->f($local->subDay()->format('Y-m-d'), $local->subDay()->format('Y-m-d'), 'simulated'))->total);
            $this->assertSame(0, $this->svc->summary($co->id, $this->f($local->addDay()->format('Y-m-d'), $local->addDay()->format('Y-m-d'), 'simulated'))->total);
            // Les écritures du grand livre suivent le même jour civil.
            $this->assertSame(5000, $this->svc->summary($co->id, $this->f($day, $day, 'simulated'))->consumedUnits());
        }
    }

    #[DataProvider('timezones')]
    public function test_default_period_is_the_last_30_days_in_the_application_timezone(string $tz): void
    {
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
        $this->travelTo(CarbonImmutable::parse('2026-11-14 23:30:00', 'UTC'));

        $p = ReportFilters::defaultPeriod();
        $today = CarbonImmutable::parse('2026-11-14 23:30:00', 'UTC')->setTimezone($tz)->startOfDay();

        $this->assertSame($today->format('Y-m-d'), $p['to']);
        $this->assertSame($today->subDays(29)->format('Y-m-d'), $p['from']);
        $this->assertSame(30, (int) CarbonImmutable::parse($p['from'])->diffInDays(CarbonImmutable::parse($p['to'])) + 1);
    }

    // --- Campagne ---------------------------------------------------------------------

    public function test_campaign_filter_covers_all_attempts_including_blocked_retries(): void
    {
        $owner = User::factory()->create();
        $co = Company::createFor($owner, 'Camp');
        $credits = app(CreditService::class);
        $credits->credit($co, 5000, 'seed'); // un seul message payable
        app(CurrentCompany::class)->set($co->fresh());
        foreach (['+243811000001', '+243811000002'] as $phone) {
            $c = Contact::create(['phone' => $phone]);
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());
        }
        $campaign = new Campaign(['name' => 'Promo', 'body' => 'Bonjour']);
        $campaign->created_by = $owner->id;
        $campaign->save();
        $other = new Campaign(['name' => 'Autre', 'body' => 'Salut']);
        $other->created_by = $owner->id;
        $other->save();

        app(CampaignService::class)->launch($campaign);
        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->status, 'à court de crédits : pause');
        $credits->credit($co, 5000, 'topup');
        app(CampaignService::class)->resume($campaign->fresh());
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);

        // Message hors campagne (envoi de test) : exclu du rapport de campagne.
        $credits->credit($co, 5000, 'topup2');
        $c3 = Contact::create(['phone' => '+243811000003']);
        app(ConsentService::class)->record($c3, 'paper', 'x', now()->subDay());
        app(SmsDispatcher::class)->dispatch($c3, 'Test', 'test:abc');

        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $s = $this->svc->summary($co->id, $this->f($today, $today, 'simulated', $campaign->id));
        $this->assertSame(2, $s->sent);
        $this->assertSame(1, $s->blocked, 'la tentative bloquée « crédits insuffisants » reste au journal et dans le rapport');
        $this->assertSame(1, $s->blockedByReason['insufficient_credits']);
        $this->assertSame(2 * 5000, $s->consumedUnits(), 'crédits de la campagne : seulement les siens');

        $all = $this->svc->summary($co->id, $this->f($today, $today, 'simulated'));
        $this->assertSame(3, $all->sent, 'sans filtre : le message de test est inclus');
        $this->assertSame(0, $this->svc->summary($co->id, $this->f($today, $today, 'simulated', $other->id))->total);
    }

    // --- Isolation --------------------------------------------------------------------

    public function test_other_company_data_is_never_included(): void
    {
        $this->msg($this->a, ['status' => 'sent']);
        $this->msg($this->b, ['status' => 'sent']);
        $this->msg($this->b, ['status' => 'failed']);
        $this->ledger($this->b, 'debit', -99999, false, '2026-11-10 10:00:00');

        $s = $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10'));
        $this->assertSame(1, $s->total);
        $this->assertSame(0, $s->debitedUnits);
        $this->assertSame(1, array_sum(array_column($this->svc->daily($this->a->id, $this->f('2026-11-10', '2026-11-10')), 'total')));
        $this->assertSame(1, $this->svc->exportCount($this->a->id, $this->f('2026-11-10', '2026-11-10')));
        $this->assertSame(2, $this->svc->summary($this->b->id, $this->f('2026-11-10', '2026-11-10'))->total);
    }

    public function test_foreign_campaign_prefix_cannot_leak_other_company_messages(): void
    {
        // Une clé « campaign:7:… » d'une autre entreprise n'apparaît jamais dans les chiffres de l'entreprise A.
        $this->msg($this->b, ['idempotency_key' => 'campaign:7:contact:1']);
        $this->msg($this->a, ['idempotency_key' => 'campaign:70:contact:1']); // préfixe voisin : ne doit pas correspondre à 7

        $this->assertSame(0, $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real', 7))->total);
        $this->assertSame(1, $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real', 70))->total);
    }

    public function test_cross_company_ledger_references_are_ignored_defence_in_depth(): void
    {
        // Données anormales (aucune clé étrangère sur sms_message_id) : une écriture de B pointant vers un message de A,
        // et une écriture de A pointant vers un message de B qui porte une clé de campagne.
        $mine = $this->msg($this->a, ['created_at' => '2026-11-10 10:00:00']);
        $theirs = $this->msg($this->b, ['idempotency_key' => 'campaign:5:contact:1', 'created_at' => '2026-11-10 10:00:00']);
        $this->ledger($this->b, 'debit', -777000, false, '2026-11-10 10:00:00', $mine);
        $this->ledger($this->a, 'debit', -5000, false, '2026-11-10 10:00:00', $theirs);
        $f = $this->f('2026-11-10', '2026-11-10');

        $row = $this->svc->exportQuery($this->a->id, $f)->first();
        $this->assertSame(0, (int) $row->net_units, 'le coût net ignore le grand livre d\'une autre entreprise');
        $this->assertSame(0, $this->svc->summary($this->a->id, $this->f('2026-11-10', '2026-11-10', 'real', 5))->debitedUnits, 'le filtre campagne du grand livre ne suit pas un message d\'une autre entreprise');
    }

    // --- Validation des filtres -----------------------------------------------------

    public function test_filters_validation(): void
    {
        $camp = new Campaign(['name' => 'X', 'body' => 'x']);
        app(CurrentCompany::class)->set($this->a);
        $camp->save();
        app(CurrentCompany::class)->set($this->b);
        $campB = new Campaign(['name' => 'Y', 'body' => 'y']);
        $campB->save();

        $ok = ['from' => '2026-11-01', 'to' => '2026-11-30', 'campaign' => null, 'mode' => 'real'];
        $this->assertTrue(ReportFilters::validator($ok, $this->a->id)->passes());
        $this->assertTrue(ReportFilters::validator(['campaign' => (string) $camp->id] + $ok, $this->a->id)->passes());

        $bad = [
            'date invalide' => ['from' => '2026-02-31'],
            'format' => ['from' => '01/11/2026'],
            'fin avant début' => ['to' => '2026-10-31'],
            'période trop longue' => ['from' => '2025-01-01'],
            'mode inconnu' => ['mode' => 'all'],
            'campagne d\'une autre entreprise' => ['campaign' => (string) $campB->id],
            'campagne inexistante' => ['campaign' => '999999'],
            'campagne non numérique' => ['campaign' => 'abc'],
            'injection' => ['from' => "2026-11-01' OR 1=1 --"],
            'date vide' => ['to' => ''],
        ];
        foreach ($bad as $label => $override) {
            $this->assertTrue(ReportFilters::validator($override + $ok, $this->a->id)->fails(), $label);
        }
    }
}
