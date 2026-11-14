<?php

namespace Tests\Support;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Insertions directes dans le journal / le grand livre avec des dates MAÎTRISÉES (heure locale de l'application, comme en base). */
trait BuildsReportData
{
    private int $phoneSeq = 0;

    /** @param array<string,mixed> $o */
    protected function msg(Company|int $company, array $o = []): int
    {
        return DB::table('sms_messages')->insertGetId($o + [
            'company_id' => $company instanceof Company ? $company->id : $company,
            'idempotency_key' => 'k:'.Str::uuid(),
            'phone' => '+24381'.str_pad((string) (2000000 + ++$this->phoneSeq), 7, '0', STR_PAD_LEFT),
            'body' => 'Bonjour',
            'status' => 'sent',
            'simulated' => false,
            'segments' => 1,
            'cost_units' => 5000,
            'created_at' => '2026-11-10 10:00:00',
            'updated_at' => '2026-11-10 10:00:00',
        ]);
    }

    /** Écriture de grand livre (montant SIGNÉ : débit négatif, remboursement positif). */
    protected function ledger(Company|int $company, string $type, int $signedUnits, bool $simulated, string $at, ?int $smsId = null): void
    {
        DB::table('credit_ledger_entries')->insert([
            'company_id' => $company instanceof Company ? $company->id : $company,
            'type' => $type,
            'amount_units' => $signedUnits,
            'balance_after_units' => 0,
            'idempotency_key' => 'l:'.Str::uuid(),
            'sms_message_id' => $smsId,
            'simulated' => $simulated,
            'created_at' => $at,
        ]);
    }
}
