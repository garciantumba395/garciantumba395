<?php

namespace App\Http\Controllers;

use App\Domain\Reports\MessageCsvExporter;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des messages. Les numéros de téléphone sont des données personnelles : réservé aux owner/admin
 * de l'entreprise ACTIVE (les membres voient les agrégats seulement). Les données sont toujours celles de l'entreprise active.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $current, ReportService $reports, MessageCsvExporter $exporter): StreamedResponse|RedirectResponse
    {
        abort_unless($current->role()?->canManageCompany(), 403);
        $companyId = $current->id() ?? abort(403);

        $validator = ReportFilters::validator($request->only(['from', 'to', 'campaign', 'mode']), $companyId);
        // Campagne forgée ou d'une autre entreprise : indiscernable d'une campagne inexistante (404).
        abort_if($validator->fails() && $validator->errors()->has('campaign'), 404);
        $filters = ReportFilters::fromValidated($validator->validate());

        $count = $reports->exportCount($companyId, $filters);
        $max = (int) config('reports.export_max_rows');
        if ($count > $max) {
            return redirect()->route('reports.index', $filters->toQuery())->with('export_error',
                'Export refusé : '.number_format($count, 0, ',', ' ').' messages sur cette période, au-delà du plafond de '
                .number_format($max, 0, ',', ' ').' lignes. Réduisez la période ou choisissez une campagne.');
        }

        $name = 'messages-'.($filters->simulated() ? 'simulation' : 'reel').'-'.$filters->from.'-'.$filters->to.'.csv';

        return response()->streamDownload(
            fn () => $exporter->write(fopen('php://output', 'w'), $companyId, $filters),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store'],
        );
    }
}
