<?php

namespace App\Domain\Credits;

use App\Models\Company;
use App\Models\CreditLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Seul code autorisé à modifier un solde. Garanties :
 *  - atomique : mise à jour conditionnelle du solde + écriture au grand livre dans la même transaction ;
 *  - jamais négatif : `UPDATE … WHERE solde >= montant` (+ contrainte CHECK sur PostgreSQL) ;
 *  - idempotent : une clé d'idempotence = une seule écriture (verrou de ligne + contrainte UNIQUE en dernier recours) ;
 *  - un remboursement n'existe qu'une fois par débit.
 */
class CreditService
{
    public function balance(Company|int $company): int
    {
        return (int) DB::table('companies')->where('id', $this->id($company))->value('credit_balance_units');
    }

    /** Ajout de crédits (administrateur de la plateforme). */
    public function credit(Company|int $company, int $units, string $key, ?string $reason = null, ?int $userId = null): CreditLedgerEntry
    {
        $this->positive($units);

        return $this->apply($company, LedgerType::Credit, $units, $key, reason: $reason, userId: $userId);
    }

    /** Correction signée (administrateur). Un retrait ne peut pas rendre le solde négatif. */
    public function adjust(Company|int $company, int $signedUnits, string $key, string $reason, ?int $userId = null): CreditLedgerEntry
    {
        if ($signedUnits === 0 || trim($reason) === '') {
            throw new InvalidArgumentException('Montant non nul et motif obligatoires.');
        }

        return $this->apply($company, LedgerType::Adjustment, $signedUnits, $key, reason: $reason, userId: $userId);
    }

    /** Débit pour l'envoi d'un message. @throws InsufficientCreditsException */
    public function debit(Company|int $company, int $units, string $key, ?int $smsMessageId = null, bool $simulated = false, ?int $userId = null): CreditLedgerEntry
    {
        $this->positive($units);

        return $this->apply($company, LedgerType::Debit, -$units, $key, smsMessageId: $smsMessageId, simulated: $simulated, userId: $userId);
    }

    /** Annule un débit (une seule fois). */
    public function refund(CreditLedgerEntry $debit, string $reason): CreditLedgerEntry
    {
        if ($debit->type !== LedgerType::Debit) {
            throw new LogicException('Seul un débit peut être remboursé.');
        }

        return $this->apply($debit->company_id, LedgerType::Refund, -$debit->amount_units, 'refund:'.$debit->idempotency_key,
            smsMessageId: $debit->sms_message_id, reason: $reason, simulated: $debit->simulated);
    }

    private function apply(Company|int $company, LedgerType $type, int $signed, string $key, ?int $smsMessageId = null, ?string $reason = null, bool $simulated = false, ?int $userId = null): CreditLedgerEntry
    {
        if ($key === '' || strlen($key) > 120) {
            throw new InvalidArgumentException('Clé d\'idempotence invalide.');
        }
        $companyId = $this->id($company);

        return DB::transaction(function () use ($companyId, $type, $signed, $key, $smsMessageId, $reason, $simulated, $userId) {
            // Verrou de ligne : sérialise les opérations d'une même entreprise (PostgreSQL).
            if (! DB::table('companies')->where('id', $companyId)->lockForUpdate()->exists()) {
                throw new LogicException('Entreprise introuvable.');
            }

            $existing = CreditLedgerEntry::withoutGlobalScopes()->where('company_id', $companyId)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing; // rejeu : aucun effet sur le solde
            }

            $query = DB::table('companies')->where('id', $companyId);
            if ($signed < 0) {
                $query->where('credit_balance_units', '>=', -$signed);
            }
            if ($query->update(['credit_balance_units' => DB::raw('credit_balance_units + '.(int) $signed)]) === 0) {
                throw new InsufficientCreditsException('Solde de crédits insuffisant.');
            }

            return CreditLedgerEntry::create([
                'company_id' => $companyId,
                'type' => $type,
                'amount_units' => $signed,
                'balance_after_units' => $this->balance($companyId),
                'idempotency_key' => $key,
                'sms_message_id' => $smsMessageId,
                'reason' => $reason ? mb_substr($reason, 0, 255) : null,
                'simulated' => $simulated,
                'created_by' => $userId,
            ]);
        });
    }

    private function positive(int $units): void
    {
        if ($units <= 0) {
            throw new InvalidArgumentException('Le montant doit être positif.');
        }
    }

    private function id(Company|int $company): int
    {
        return $company instanceof Company ? $company->id : $company;
    }
}
