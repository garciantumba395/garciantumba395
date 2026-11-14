<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Credits\CreditService;
use App\Domain\Credits\CreditUnits;
use App\Domain\Credits\InsufficientCreditsException;
use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
use App\Livewire\Concerns\ThrottlesAdminActions;
use App\Models\Company;
use App\Models\CreditLedgerEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administration — entreprises et crédits')]
class Companies extends Component
{
    use ThrottlesAdminActions;

    public ?int $companyId = null;

    public string $operation = 'credit';   // credit | retrait

    public string $amount = '';

    public string $reason = '';

    public string $sendingStatus = 'probation';

    public string $sendingReason = '';

    public string $dailyLimit = '';

    // Recherche (nom) et filtre par statut d'envoi de la liste.
    public string $search = '';

    public string $statusFilter = '';

    /** Clé d'idempotence de la tentative en cours : un double clic n'ajoute les crédits qu'une fois. */
    #[Locked]
    public string $attemptKey = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->attemptKey = (string) Str::uuid();
    }

    public function submit(CreditService $credits, AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $this->throttleAdminAction();
        $this->validate([
            'companyId' => ['required', 'integer', 'exists:companies,id'],
            'operation' => ['required', 'in:credit,retrait'],
            'amount' => ['required', 'string', 'max:16'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $units = CreditUnits::fromCredits($this->amount);
            if ($units <= 0 || $units > config('credits.max_adjustment_credits') * config('credits.units_per_credit')) {
                throw new InvalidArgumentException('Montant hors limites.');
            }
            $key = 'admin:'.$this->attemptKey;
            $entry = $this->operation === 'credit'
                ? $credits->credit($this->companyId, $units, $key, $this->reason, Auth::id())
                : $credits->adjust($this->companyId, -$units, $key, $this->reason, Auth::id());
            // Audit : uniquement si l'écriture vient d'être créée (un rejeu idempotent ne double pas l'entrée d'audit).
            if ($entry->wasRecentlyCreated) {
                $audit->record($this->operation === 'credit' ? 'credits.added' : 'credits.removed', $this->companyId, $entry, [
                    'units' => $units, 'balance_after_units' => $entry->balance_after_units, 'reason' => $this->reason,
                ]);
            }
        } catch (InvalidArgumentException|InsufficientCreditsException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->attemptKey = (string) Str::uuid();
        $this->reset('amount', 'reason');
        session()->flash('status', 'Opération enregistrée.');
    }

    public function updatedCompanyId(): void
    {
        $company = $this->companyId ? Company::find($this->companyId) : null;
        $this->sendingStatus = $company?->sending_status->value ?? 'probation';
        $this->dailyLimit = (string) ($company?->daily_send_limit ?? '');
        $this->sendingReason = '';
    }

    /** Statut d'envoi réel et surcharge de la limite journalière (motif obligatoire, administrateur uniquement). */
    public function updateSending(SendingPolicy $policy, AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $this->throttleAdminAction();
        $this->validate([
            'companyId' => ['required', 'integer', 'exists:companies,id'],
            'sendingStatus' => ['required', 'in:probation,validated,suspended'],
            'sendingReason' => ['required', 'string', 'max:255'],
            'dailyLimit' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $company = Company::findOrFail($this->companyId);
        $oldLimit = $company->daily_send_limit;
        $policy->setStatus($company, SendingStatus::from($this->sendingStatus), $this->sendingReason); // audité par SendingPolicy
        $newLimit = $this->dailyLimit === '' ? null : (int) $this->dailyLimit;
        $company->forceFill(['daily_send_limit' => $newLimit])->save();
        if ($oldLimit !== $newLimit) {
            $audit->record('sending.limit_changed', $company, $company, ['from' => $oldLimit, 'to' => $newLimit, 'reason' => $this->sendingReason]);
        }

        $this->reset('sendingReason');
        session()->flash('status', 'Statut d\'envoi mis à jour.');
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.companies', [
            'companies' => Company::withCount('users')
                ->when(trim($this->search) !== '', fn ($q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower(str_replace(['%', '_'], '', trim($this->search))).'%']))
                ->when(SendingStatus::tryFrom($this->statusFilter), fn ($q, $s) => $q->where('sending_status', $s->value))
                ->orderBy('name')->get(),
            // Vue plateforme : lecture explicite hors du filtre par entreprise.
            'entries' => $this->companyId
                ? CreditLedgerEntry::withoutGlobalScopes()->where('company_id', $this->companyId)->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->is_platform_admin, 403);
    }
}
