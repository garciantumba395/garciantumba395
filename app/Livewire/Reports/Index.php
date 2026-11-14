<?php

namespace App\Livewire\Reports;

use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportService;
use App\Models\Campaign;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rapports d'envoi. Lecture seule, tous rôles (agrégats). L'état Livewire vient du client : les filtres sont
 * revalidés à CHAQUE rendu (dates, mode, campagne appartenant à l'entreprise active), l'export est un contrôleur à part (owner/admin).
 */
#[Layout('layouts.app')]
#[Title('Rapports')]
class Index extends Component
{
    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    #[Url(as: 'campaign')]
    public string $campaign = '';

    #[Url(as: 'mode')]
    public string $mode = ReportFilters::MODE_REAL;

    public function mount(): void
    {
        $default = ReportFilters::defaultPeriod();
        $this->from = $this->from !== '' ? $this->from : $default['from'];
        $this->to = $this->to !== '' ? $this->to : $default['to'];
    }

    public function apply(): void
    {
        // Le rendu qui suit valide et applique les filtres.
    }

    public function showMode(string $mode): void
    {
        $this->mode = in_array($mode, [ReportFilters::MODE_REAL, ReportFilters::MODE_SIMULATED], true) ? $mode : ReportFilters::MODE_REAL;
    }

    public function render(CurrentCompany $current, ReportService $reports)
    {
        $companyId = (int) $current->id();
        $validator = ReportFilters::validator(
            ['from' => $this->from, 'to' => $this->to, 'campaign' => $this->campaign === '' ? null : $this->campaign, 'mode' => $this->mode],
            $companyId,
        );

        $data = [
            'valid' => false, 'canExport' => (bool) $current->role()?->canManageCompany(),
            'campaigns' => collect(), 'summary' => null, 'daily' => [], 'otherNatureHasData' => false, 'campaignName' => null,
            'exportUrl' => null, 'blockReasons' => ReportService::BLOCK_REASONS, 'simulated' => $this->mode === ReportFilters::MODE_SIMULATED,
        ];

        $selected = ctype_digit($this->campaign) ? (int) $this->campaign : null;
        $campaigns = Campaign::orderByDesc('id')->limit(200)->get(['id', 'name']);
        if ($selected && ! $campaigns->contains('id', $selected) && ($extra = Campaign::find($selected, ['id', 'name']))) {
            $campaigns->push($extra);
        }
        $data['campaigns'] = $campaigns;

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return view('livewire.reports.index', $data);
        }
        $this->resetErrorBag();

        $filters = ReportFilters::fromValidated($validator->validated());
        $summary = $reports->summary($companyId, $filters);
        $data['valid'] = true;
        $data['summary'] = $summary;
        $data['daily'] = $summary->isEmpty() ? [] : $reports->daily($companyId, $filters);
        $data['maxDayTotal'] = $data['daily'] ? max(1, ...array_column($data['daily'], 'total')) : 1;
        $data['campaignName'] = $filters->campaignId ? $campaigns->firstWhere('id', $filters->campaignId)?->name : null;
        $data['filters'] = $filters;
        $data['exportUrl'] = $data['canExport'] ? route('reports.export', $filters->toQuery()) : null;

        if ($summary->isEmpty()) {
            // Indication seulement (jamais de chiffre ni de total) : l'autre nature d'envoi a-t-elle des données ?
            $other = new ReportFilters($filters->from, $filters->to, $filters->campaignId,
                $filters->simulated() ? ReportFilters::MODE_REAL : ReportFilters::MODE_SIMULATED);
            $data['otherNatureHasData'] = ! $reports->summary($companyId, $other)->isEmpty();
        }

        return view('livewire.reports.index', $data);
    }
}
