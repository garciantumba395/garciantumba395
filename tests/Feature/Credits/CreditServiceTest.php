<?php

namespace Tests\Feature\Credits;

use App\Domain\Credits\CreditService;
use App\Domain\Credits\InsufficientCreditsException;
use App\Domain\Credits\LedgerType;
use App\Models\Company;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private CreditService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        $this->svc = app(CreditService::class);
    }

    private function sum(Company $c): int
    {
        return (int) CreditLedgerEntry::withoutGlobalScopes()->where('company_id', $c->id)->sum('amount_units');
    }

    public function test_credit_increases_balance_and_writes_ledger(): void
    {
        $admin = User::factory()->create();
        $e = $this->svc->credit($this->a, 600000, 'k1', 'Paiement #1', $admin->id);

        $this->assertSame(600000, $this->svc->balance($this->a));
        $this->assertSame(LedgerType::Credit, $e->type);
        $this->assertSame(600000, $e->balance_after_units);
        $this->assertSame('Paiement #1', $e->reason);
        $this->assertSame($admin->id, $e->created_by);
    }

    public function test_debit_decreases_balance_and_records_balance_after(): void
    {
        $this->svc->credit($this->a, 10000, 'c');
        $e = $this->svc->debit($this->a, 4000, 'd1', 55, true);

        $this->assertSame(6000, $this->svc->balance($this->a));
        $this->assertSame(-4000, $e->amount_units);
        $this->assertSame(6000, $e->balance_after_units);
        $this->assertTrue($e->simulated);
        $this->assertSame(55, $e->sms_message_id);
    }

    public function test_balance_can_never_go_negative(): void
    {
        $this->svc->credit($this->a, 5000, 'c');

        try {
            $this->svc->debit($this->a, 5001, 'd1');
            $this->fail('Exception attendue');
        } catch (InsufficientCreditsException) {
            $this->assertTrue(true);
        }

        $this->assertSame(5000, $this->svc->balance($this->a));
        $this->assertSame(1, CreditLedgerEntry::withoutGlobalScopes()->count()); // aucune écriture pour le débit refusé
        $this->svc->debit($this->a, 5000, 'd2'); // exactement le solde : autorisé
        $this->assertSame(0, $this->svc->balance($this->a));
    }

    public function test_same_key_never_debits_twice(): void
    {
        $this->svc->credit($this->a, 20000, 'c');

        $first = $this->svc->debit($this->a, 5000, 'sms:1');
        $again = $this->svc->debit($this->a, 5000, 'sms:1');
        $other = $this->svc->debit($this->a, 9999, 'sms:1'); // même clé, montant différent : toujours la 1re écriture

        $this->assertSame($first->id, $again->id);
        $this->assertSame($first->id, $other->id);
        $this->assertSame(15000, $this->svc->balance($this->a));
        $this->assertSame(2, CreditLedgerEntry::withoutGlobalScopes()->count());
    }

    public function test_same_key_replay_works_even_when_balance_is_now_too_low(): void
    {
        $this->svc->credit($this->a, 5000, 'c');
        $first = $this->svc->debit($this->a, 5000, 'sms:1');

        $this->assertSame($first->id, $this->svc->debit($this->a, 5000, 'sms:1')->id); // pas d'exception : rejeu
    }

    public function test_keys_are_independent_between_companies(): void
    {
        $this->svc->credit($this->a, 10000, 'c');
        $this->svc->credit($this->b, 10000, 'c');
        $this->svc->debit($this->a, 3000, 'sms:1');
        $this->svc->debit($this->b, 3000, 'sms:1');

        $this->assertSame(7000, $this->svc->balance($this->a));
        $this->assertSame(7000, $this->svc->balance($this->b));
    }

    public function test_refund_is_issued_once_and_restores_balance(): void
    {
        $this->svc->credit($this->a, 10000, 'c');
        $debit = $this->svc->debit($this->a, 5000, 'sms:1', 9, true);

        $r1 = $this->svc->refund($debit, 'refus');
        $r2 = $this->svc->refund($debit, 'refus');

        $this->assertSame($r1->id, $r2->id);
        $this->assertSame(10000, $this->svc->balance($this->a));
        $this->assertSame(LedgerType::Refund, $r1->type);
        $this->assertSame(5000, $r1->amount_units);
        $this->assertTrue($r1->simulated);
    }

    public function test_only_debits_can_be_refunded(): void
    {
        $credit = $this->svc->credit($this->a, 1000, 'c');
        $this->expectException(LogicException::class);
        $this->svc->refund($credit, 'x');
    }

    public function test_adjustment_requires_reason_and_cannot_overdraw(): void
    {
        $this->svc->credit($this->a, 5000, 'c');

        $this->svc->adjust($this->a, -2000, 'a1', 'Erreur de saisie');
        $this->assertSame(3000, $this->svc->balance($this->a));

        $this->expectException(InsufficientCreditsException::class);
        $this->svc->adjust($this->a, -3001, 'a2', 'Trop');
    }

    public function test_invalid_arguments(): void
    {
        foreach ([fn () => $this->svc->credit($this->a, 0, 'k'), fn () => $this->svc->credit($this->a, -5, 'k'),
            fn () => $this->svc->debit($this->a, 0, 'k'), fn () => $this->svc->credit($this->a, 10, ''),
            fn () => $this->svc->credit($this->a, 10, str_repeat('k', 121)), fn () => $this->svc->adjust($this->a, 10, 'k', ' '),
            fn () => $this->svc->adjust($this->a, 0, 'k', 'x')] as $call) {
            try {
                $call();
                $this->fail('Exception attendue');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, CreditLedgerEntry::withoutGlobalScopes()->count());
    }

    public function test_balance_always_equals_sum_of_ledger(): void
    {
        $this->svc->credit($this->a, 100000, 'c1');
        $d = $this->svc->debit($this->a, 5000, 'd1');
        $this->svc->debit($this->a, 7000, 'd2');
        $this->svc->refund($d, 'x');
        $this->svc->adjust($this->a, -1000, 'a1', 'corr');
        try {
            $this->svc->debit($this->a, 9999999, 'd3');
        } catch (InsufficientCreditsException) {
        }
        $this->svc->credit($this->b, 42, 'c1');

        $this->assertSame($this->sum($this->a), $this->svc->balance($this->a));
        $this->assertSame($this->sum($this->b), $this->svc->balance($this->b));
        $this->artisan('credits:verify')->assertSuccessful();
    }

    public function test_verify_command_detects_tampering(): void
    {
        $this->svc->credit($this->a, 1000, 'c');
        \DB::table('companies')->where('id', $this->a->id)->update(['credit_balance_units' => 999999]); // altération manuelle

        $this->artisan('credits:verify')->assertFailed();
    }

    public function test_ledger_is_append_only(): void
    {
        $e = $this->svc->credit($this->a, 1000, 'c');

        try {
            $e->update(['amount_units' => 5]);
            $this->fail('Exception attendue');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        $this->expectException(LogicException::class);
        $e->delete();
    }

    public function test_database_unique_key_is_the_last_line_of_defence(): void
    {
        $this->svc->credit($this->a, 1000, 'dup');
        $this->expectException(QueryException::class);
        \DB::table('credit_ledger_entries')->insert([
            'company_id' => $this->a->id, 'type' => 'credit', 'amount_units' => 1, 'balance_after_units' => 1,
            'idempotency_key' => 'dup', 'created_at' => now(),
        ]);
    }

    public function test_postgres_check_constraint_blocks_negative_balance(): void
    {
        if (\DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Contrainte CHECK : PostgreSQL uniquement.');
        }
        $this->expectException(QueryException::class);
        \DB::table('companies')->where('id', $this->a->id)->update(['credit_balance_units' => -1]);
    }
}
