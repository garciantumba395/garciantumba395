<?php

namespace App\Domain\Reports;

use App\Domain\Credits\LedgerType;
use App\Domain\Sms\SmsStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats de rapports. Règles :
 *  - chiffres issus UNIQUEMENT de `sms_messages` et `credit_ledger_entries`, agrégés par la base (GROUP BY) ;
 *  - `company_id` est TOUJOURS explicite dans chaque requête (en plus de l'isolation applicative : requêtes `DB::table`,
 *    donc sans le scope Eloquent) ; l'appelant fournit l'identifiant de l'entreprise ACTIVE ;
 *  - `simulated` est TOUJOURS filtré : un résultat ne mélange jamais envois simulés et réels ;
 *  - le jour d'un message est celui de `created_at`, lu tel que stocké (fuseau de l'application, jamais converti en UTC).
 */
class ReportService
{
    /** Motifs de blocage connus (clé stockée => libellé). Tout autre motif est regroupé sous « other ». */
    public const BLOCK_REASONS = [
        'no_consent' => 'Sans consentement',
        'suppressed' => 'Désinscrit',
        'insufficient_credits' => 'Crédits insuffisants',
        'daily_limit' => 'Limite journalière atteinte',
        'suspended' => 'Envoi suspendu',
        'other' => 'Autre motif',
    ];

    public function summary(int $companyId, ReportFilters $f): ReportSummary
    {
        $counts = self::blankCounts();
        $byReason = array_fill_keys(array_keys(self::BLOCK_REASONS), 0);

        $rows = $this->messages($companyId, $f)
            ->selectRaw('status, blocked_reason, count(*) as n')
            ->groupBy('status', 'blocked_reason')
            ->get();

        foreach ($rows as $row) {
            self::tally($counts, (string) $row->status, (int) $row->n);
            if ($row->status === SmsStatus::Blocked->value) {
                $reason = (string) $row->blocked_reason;
                $byReason[isset(self::BLOCK_REASONS[$reason]) ? $reason : 'other'] += (int) $row->n;
            }
        }

        $ledger = $this->ledger($companyId, $f)
            ->selectRaw('type, count(*) as n, sum(amount_units) as units')
            ->groupBy('type')
            ->get()->keyBy('type');

        // Débit : montant négatif ; remboursement : montant positif.
        $debited = -(int) ($ledger[LedgerType::Debit->value]->units ?? 0);
        $refunded = (int) ($ledger[LedgerType::Refund->value]->units ?? 0);
        $entries = (int) ($ledger[LedgerType::Debit->value]->n ?? 0) + (int) ($ledger[LedgerType::Refund->value]->n ?? 0);

        return new ReportSummary(
            $counts['total'], $counts['sent'], $counts['delivered'], $counts['failed'], $counts['unknown'],
            $counts['in_progress'], $counts['blocked'], $byReason, $debited, $refunded, $entries,
        );
    }

    /**
     * Série journalière (jours civils du fuseau de l'application), triée par date croissante.
     * Seuls les jours comportant au moins un message ou une écriture de grand livre sont listés.
     *
     * @return list<array{day:string,total:int,sent:int,delivered:int,failed:int,unknown:int,blocked:int,in_progress:int,consumed_units:int}>
     */
    public function daily(int $companyId, ReportFilters $f): array
    {
        $days = [];

        $messages = $this->messages($companyId, $f)
            ->selectRaw('DATE(created_at) as day, status, count(*) as n')
            ->groupByRaw('DATE(created_at), status')
            ->get();
        foreach ($messages as $row) {
            $day = (string) $row->day;
            $days[$day] ??= self::blankCounts();
            self::tally($days[$day], (string) $row->status, (int) $row->n);
        }

        $ledger = $this->ledger($companyId, $f)
            ->selectRaw('DATE(created_at) as day, sum(-amount_units) as units')
            ->groupByRaw('DATE(created_at)')
            ->get();
        foreach ($ledger as $row) {
            $day = (string) $row->day;
            $days[$day] ??= self::blankCounts();
            $days[$day]['consumed_units'] += (int) $row->units; // débit (−) et remboursement (+) : somme = débits − remboursements
        }

        ksort($days);

        return array_map(fn ($day, $values) => ['day' => $day] + $values, array_keys($days), $days);
    }

    /** Nombre de messages de l'export (même filtre que le rapport). */
    public function exportCount(int $companyId, ReportFilters $f): int
    {
        return $this->messages($companyId, $f)->count();
    }

    /**
     * Messages de l'export, avec le coût NET en unités (débits − remboursements du grand livre, 0 si aucune écriture).
     * Lecture en lots par identifiant (mémoire constante) : l'appelant itère avec `lazyById()`.
     */
    public function exportQuery(int $companyId, ReportFilters $f): Builder
    {
        return $this->messages($companyId, $f)->selectRaw(
            'sms_messages.id, sms_messages.created_at, sms_messages.phone, sms_messages.idempotency_key, sms_messages.status, '
            .'sms_messages.blocked_reason, sms_messages.segments, sms_messages.simulated, '
            .'coalesce((select -sum(l.amount_units) from credit_ledger_entries l where l.company_id = sms_messages.company_id '
            ."and l.sms_message_id = sms_messages.id and l.type in ('debit', 'refund')), 0) as net_units"
        );
    }

    private static function blankCounts(): array
    {
        return ['total' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0, 'unknown' => 0, 'blocked' => 0, 'in_progress' => 0, 'consumed_units' => 0];
    }

    /** Classe $n messages de statut $status dans les compteurs. « envoyés » = sent + delivered. */
    private static function tally(array &$c, string $status, int $n): void
    {
        $c['total'] += $n;
        match ($status) {
            SmsStatus::Delivered->value => [$c['delivered'] += $n, $c['sent'] += $n],
            SmsStatus::Sent->value => $c['sent'] += $n,
            SmsStatus::Failed->value => $c['failed'] += $n,
            SmsStatus::Unknown->value => $c['unknown'] += $n,
            SmsStatus::Blocked->value => $c['blocked'] += $n,
            default => $c['in_progress'] += $n, // pending, sending
        };
    }

    /** Un résultat « mode » ne contient JAMAIS l'autre nature d'envoi : `simulated` est toujours filtré. */
    private function messages(int $companyId, ReportFilters $f): Builder
    {
        $q = DB::table('sms_messages')
            ->where('sms_messages.company_id', $companyId)
            ->where('sms_messages.simulated', $f->simulated())
            ->where('sms_messages.created_at', '>=', $f->startSql())
            ->where('sms_messages.created_at', '<', $f->endExclusiveSql());

        if ($prefix = $f->campaignKeyPrefix()) {
            $q->where('sms_messages.idempotency_key', 'like', $prefix.'%');
        }

        return $q;
    }

    /** Débits et remboursements du bloc (même nature `simulated`), datés par l'écriture du grand livre. */
    private function ledger(int $companyId, ReportFilters $f): Builder
    {
        $q = DB::table('credit_ledger_entries')
            ->where('credit_ledger_entries.company_id', $companyId)
            ->where('credit_ledger_entries.simulated', $f->simulated())
            ->whereIn('credit_ledger_entries.type', [LedgerType::Debit->value, LedgerType::Refund->value])
            ->where('credit_ledger_entries.created_at', '>=', $f->startSql())
            ->where('credit_ledger_entries.created_at', '<', $f->endExclusiveSql());

        if ($prefix = $f->campaignKeyPrefix()) {
            $q->whereIn('credit_ledger_entries.sms_message_id', fn ($sub) => $sub->from('sms_messages')->select('id')
                ->where('company_id', $companyId)->where('idempotency_key', 'like', $prefix.'%'));
        }

        return $q;
    }
}
