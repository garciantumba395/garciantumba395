<?php

namespace Tests\Feature\Credits;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CostCalculator;
use App\Domain\Credits\CreditService;
use App\Domain\Credits\LedgerType;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CreditLedgerEntry;
use App\Models\SmsRate;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CostAndDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private CreditService $credits;

    private SmsDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->credits = app(CreditService::class);
        app(CurrentCompany::class)->set($this->a);
        $this->dispatcher = app(SmsDispatcher::class);
    }

    private function eligible(string $phone = '+243812345678'): Contact
    {
        $c = Contact::create(['phone' => $phone]);
        app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());

        return $c;
    }

    // --- Tarifs par pays / opérateur ----------------------------------------------

    public function test_default_rate_applies_when_no_rate_is_configured(): void
    {
        $cost = app(CostCalculator::class)->forMessage('+243812345678', 'Bonjour');

        $this->assertSame(1, $cost->segments);
        $this->assertSame(5000, $cost->totalUnits());
        $this->assertNull($cost->rateLabel);
    }

    public function test_cost_is_segments_times_rate(): void
    {
        $cost = app(CostCalculator::class)->forMessage('+243812345678', str_repeat('a', 161));
        $this->assertSame(2, $cost->segments);
        $this->assertSame(10000, $cost->totalUnits());
    }

    public function test_rate_resolution_prefix_beats_country_beats_default(): void
    {
        SmsRate::create(['country' => 'CD', 'prefix' => null, 'label' => 'RDC', 'units_per_segment' => 6000]);
        SmsRate::create(['country' => 'CD', 'prefix' => '+24381', 'label' => 'Opérateur X', 'units_per_segment' => 4000]);
        SmsRate::create(['country' => 'CD', 'prefix' => '+243811', 'label' => 'Opérateur X (sous-plage)', 'units_per_segment' => 3000]);
        $calc = app(CostCalculator::class);

        $this->assertSame([3000, 'Opérateur X (sous-plage)'], $calc->rateFor('+243811234567')); // préfixe le plus long
        $this->assertSame([4000, 'Opérateur X'], $calc->rateFor('+243812345678'));
        $this->assertSame([6000, 'RDC'], $calc->rateFor('+243971234567'));            // tarif pays
        $this->assertSame([5000, null], $calc->rateFor('+33612345678'));              // autre pays : défaut
    }

    public function test_inactive_rates_and_other_countries_prefixes_are_ignored(): void
    {
        SmsRate::create(['country' => 'CD', 'prefix' => null, 'units_per_segment' => 1000, 'is_active' => false]);
        SmsRate::create(['country' => 'FR', 'prefix' => '+3361', 'units_per_segment' => 1]);

        $this->assertSame([5000, null], app(CostCalculator::class)->rateFor('+243812345678'));
        $this->assertSame([1, '+3361'], app(CostCalculator::class)->rateFor('+33612345678'));
    }

    // --- Débit à l'envoi ---------------------------------------------------------

    public function test_send_debits_exact_cost_and_records_it_on_the_message(): void
    {
        $this->credits->credit($this->a, 600000, 'seed'); // 100 crédits
        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Sent, $m->status);
        $this->assertSame([1, 'gsm7', 5000], [$m->segments, $m->encoding, $m->cost_units]);
        $this->assertSame(595000, $this->credits->balance($this->a));
        $debit = CreditLedgerEntry::where('type', LedgerType::Debit->value)->first();
        $this->assertSame(-5000, $debit->amount_units);
        $this->assertSame($m->id, $debit->sms_message_id);
        $this->assertTrue($debit->simulated);
    }

    public function test_120_messages_consume_exactly_100_credits(): void
    {
        $this->credits->credit($this->a, 600000, 'seed');
        $contact = $this->eligible();

        for ($i = 1; $i <= 120; $i++) {
            $this->assertSame(SmsStatus::Sent, $this->dispatcher->dispatch($contact, 'Bonjour', "k$i")->status);
        }

        $this->assertSame(0, $this->credits->balance($this->a));
        $this->assertSame(SmsStatus::Blocked, $this->dispatcher->dispatch($contact, 'Bonjour', 'k121')->status);
    }

    public function test_multi_segment_message_costs_more(): void
    {
        $this->credits->credit($this->a, 600000, 'seed');
        $m = $this->dispatcher->dispatch($this->eligible(), str_repeat('ç', 150), 'k1'); // UCS-2, 3 segments

        $this->assertSame([3, 'ucs2', 15000], [$m->segments, $m->encoding, $m->cost_units]);
        $this->assertSame(585000, $this->credits->balance($this->a));
    }

    public function test_duplicate_dispatch_never_debits_twice(): void
    {
        $this->credits->credit($this->a, 600000, 'seed');
        $c = $this->eligible();

        $this->dispatcher->dispatch($c, 'Bonjour', 'camp:1');
        $this->dispatcher->dispatch($c, 'Bonjour', 'camp:1');
        $this->dispatcher->dispatch($c, 'Bonjour', 'camp:1');

        $this->assertSame(595000, $this->credits->balance($this->a));
        $this->assertSame(1, CreditLedgerEntry::where('type', LedgerType::Debit->value)->count());
    }

    public function test_insufficient_credits_blocks_without_sending_or_debiting(): void
    {
        $this->credits->credit($this->a, 4999, 'seed');
        $m = $this->dispatcher->dispatch($this->eligible(), 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertSame('insufficient_credits', $m->blocked_reason);
        $this->assertSame(4999, $this->credits->balance($this->a));
        $this->assertCount(0, app(SmsManager::class)->provider()->sentRequests());
    }

    public function test_consent_block_never_debits(): void
    {
        $this->credits->credit($this->a, 600000, 'seed');
        $m = $this->dispatcher->dispatch(Contact::create(['phone' => '+243812345678']), 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Blocked, $m->status);
        $this->assertSame(600000, $this->credits->balance($this->a));
    }

    public function test_definitive_rejection_is_refunded_once(): void
    {
        config(['sms.fake.reject_numbers' => ['+243812345678']]);
        $this->credits->credit($this->a, 600000, 'seed');
        $c = $this->eligible();

        $m = $this->dispatcher->dispatch($c, 'Bonjour', 'k1');
        $this->dispatcher->dispatch($c, 'Bonjour', 'k1'); // rejeu

        $this->assertSame(SmsStatus::Failed, $m->status);
        $this->assertSame(600000, $this->credits->balance($this->a));
        $this->assertSame(1, CreditLedgerEntry::where('type', LedgerType::Refund->value)->count());
    }

    public function test_transport_error_keeps_the_debit_and_never_refunds_automatically(): void
    {
        config(['sms.fake.throw_numbers' => ['+243812345678']]);
        $this->credits->credit($this->a, 600000, 'seed');
        $c = $this->eligible();

        $m = $this->dispatcher->dispatch($c, 'Bonjour', 'k1');

        $this->assertSame(SmsStatus::Unknown, $m->status);
        $this->assertSame(595000, $this->credits->balance($this->a)); // débité : le message a peut-être été envoyé
        $this->assertSame(0, CreditLedgerEntry::where('type', LedgerType::Refund->value)->count());
    }

    public function test_ledger_and_balance_stay_consistent_after_mixed_activity(): void
    {
        $this->credits->credit($this->a, 600000, 'seed');
        $c = $this->eligible();
        $this->dispatcher->dispatch($c, 'Un', 'k1');
        config(['sms.fake.reject_numbers' => ['+243822222222']]);
        $this->dispatcher->dispatch($this->eligible('+243822222222'), 'Deux', 'k2');
        $this->dispatcher->dispatch(Contact::create(['phone' => '+243833333333']), 'Trois', 'k3');

        $this->artisan('credits:verify')->assertSuccessful();
        $this->assertSame(595000, $this->credits->balance($this->a));
    }

    public function test_company_never_sees_another_companys_ledger(): void
    {
        $b = Company::createFor(User::factory()->create(), 'B');
        $this->credits->credit($this->a, 1000, 'k');
        $this->credits->credit($b, 2000, 'k');

        $this->assertSame(1, CreditLedgerEntry::count());
        app(CurrentCompany::class)->set($b);
        $this->assertSame(2000, CreditLedgerEntry::first()->amount_units);
    }
}
