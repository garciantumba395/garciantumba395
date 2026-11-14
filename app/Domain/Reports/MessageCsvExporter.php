<?php

namespace App\Domain\Reports;

use App\Domain\Credits\CreditUnits;
use App\Domain\Sms\SmsStatus;
use Illuminate\Support\Facades\DB;

/**
 * Export CSV des messages d'une période (UTF-8 avec BOM pour Excel, séparateur `config('reports.csv_delimiter')`).
 * Écrit au fil de l'eau (lecture par lots) : la mémoire ne dépend pas du nombre de lignes.
 * Contient des NUMÉROS DE TÉLÉPHONE (données personnelles) : l'appelant doit avoir vérifié le rôle owner/admin.
 */
class MessageCsvExporter
{
    public const HEADER = ['Date', 'Numéro', 'Campagne', 'Statut', 'Motif de blocage', 'Segments', 'Coût en crédits', 'Simulé'];

    public function __construct(private ReportService $reports) {}

    /** @param resource $out */
    public function write($out, int $companyId, ReportFilters $f): void
    {
        $delimiter = (string) config('reports.csv_delimiter', ';');
        if (strlen($delimiter) !== 1) {
            $delimiter = ';';
        }

        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel reconnaît l'encodage
        $this->line($out, $delimiter, self::HEADER);

        $campaigns = DB::table('campaigns')->where('company_id', $companyId)->pluck('name', 'id');

        $rows = $this->reports->exportQuery($companyId, $f)->lazyById((int) config('reports.export_chunk', 1000), 'sms_messages.id', 'id');
        foreach ($rows as $row) {
            $campaignName = '';
            if (preg_match('/^campaign:(\d+):/', (string) $row->idempotency_key, $m)) {
                $campaignName = (string) ($campaigns[(int) $m[1]] ?? '');
            }

            $this->line($out, $delimiter, [
                (string) $row->created_at,
                (string) $row->phone,
                $campaignName,
                SmsStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status,
                $row->blocked_reason === null ? '' : (ReportService::BLOCK_REASONS[$row->blocked_reason] ?? (string) $row->blocked_reason),
                $row->segments === null ? '' : (string) (int) $row->segments,
                CreditUnits::format((int) $row->net_units),
                $row->simulated ? 'oui' : 'non',
            ]);
        }
    }

    /** @param resource $out */
    private function line($out, string $delimiter, array $cells): void
    {
        // Chaque cellule passe par la neutralisation des formules, sans exception.
        fputcsv($out, array_map(CsvCell::safe(...), $cells), $delimiter, '"', '', "\r\n");
    }
}
