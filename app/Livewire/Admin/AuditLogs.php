<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AuthorizesPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Visionneuse du journal d'audit : LECTURE SEULE, paginée, filtrable. */
#[Layout('layouts.app')]
#[Title('Administration — journal d\'audit')]
class AuditLogs extends Component
{
    use AuthorizesPlatformAdmin, WithPagination;

    public string $action = '';

    public string $companyId = '';

    /** Identifiant d'utilisateur, ou « system » pour les actions automatiques. */
    public string $actorId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeAdmin();
        $from = $this->parseDate('dateFrom', $this->dateFrom)?->startOfDay();
        $to = $this->parseDate('dateTo', $this->dateTo)?->endOfDay();
        $company = ctype_digit($this->companyId) ? (int) $this->companyId : null;
        $actor = ctype_digit($this->actorId) ? (int) $this->actorId : null;

        $logs = AuditLog::with(['actor:id,name', 'company:id,name'])
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($company !== null, fn ($q) => $q->where('company_id', $company))
            ->when($this->actorId === 'system', fn ($q) => $q->whereNull('actor_id'))
            ->when($actor !== null, fn ($q) => $q->where('actor_id', $actor))
            // Bornes en fuseau de l'application, sans conversion UTC.
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('id')->paginate(25);

        return view('livewire.admin.audit-logs', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function parseDate(string $field, string $value): ?Carbon
    {
        $this->resetErrorBag($field);
        if ($value === '') {
            return null;
        }
        try {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone')) : false;
        } catch (\Throwable) {
            $date = false;
        }
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->addError($field, 'Date invalide.');

            return null;
        }

        return $date;
    }
}
