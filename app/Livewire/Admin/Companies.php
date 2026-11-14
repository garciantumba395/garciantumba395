<?php

namespace App\Livewire\Admin;

use App\Domain\Credits\CreditService;
use App\Domain\Credits\CreditUnits;
use App\Domain\Credits\InsufficientCreditsException;
use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
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
    public ?int $companyId = null;

    public string $operation = 'credit';   // credit | retrait

    public string $amount = '';

    public string $reason = '';

    public string $sendingStatus = 'probation';

    public string $sendingReason = '';

    public string $dailyLimit = '';

    /** Clé d'idempotence de la tentative en cours : un double clic n'ajoute les crédits qu'une fois. */
    #[Locked]
    public string $attemptKey = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->attemptKey = (string) Str::uuid();
    }

    public function submit(CreditService $credits): void
    {
        $this->authorizeAdmin();
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
            $this->operation === 'credit'
                ? $credits->credit($this->companyId, $units, $key, $this->reason, Auth::id())
                : $credits->adjust($this->companyId, -$units, $key, $this->reason, Auth::id());
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
    public function updateSending(SendingPolicy $policy): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'companyId' => ['required', 'integer', 'exists:companies,id'],
            'sendingStatus' => ['required', 'in:probation,validated,suspended'],
            'sendingReason' => ['required', 'string', 'max:255'],
            'dailyLimit' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $company = Company::findOrFail($this->companyId);
        $policy->setStatus($company, SendingStatus::from($this->sendingStatus), $this->sendingReason);
        $company->forceFill(['daily_send_limit' => $this->dailyLimit === '' ? null : (int) $this->dailyLimit])->save();

        $this->reset('sendingReason');
        session()->flash('status', 'Statut d\'envoi mis à jour.');
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.companies', [
            'companies' => Company::withCount('users')->orderBy('name')->get(),
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
