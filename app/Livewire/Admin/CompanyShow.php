<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AccountService;
use App\Livewire\Admin\Concerns\AuthorizesPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Company;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administration — fiche entreprise')]
class CompanyShow extends Component
{
    use AuthorizesPlatformAdmin;

    #[Locked]
    public int $companyId;

    public string $reason = '';

    public function mount(int $company): void
    {
        $this->authorizeAdmin();
        $this->companyId = Company::findOrFail($company)->id;
    }

    public function disable(AccountService $accounts): void
    {
        $this->authorizeAdmin();
        $this->change(fn (Company $c) => $accounts->disableCompany($c, $this->reason), 'Entreprise désactivée : ses membres ne peuvent plus l\'utiliser.');
    }

    public function enable(AccountService $accounts): void
    {
        $this->authorizeAdmin();
        $this->change(fn (Company $c) => $accounts->enableCompany($c, $this->reason), 'Entreprise réactivée.');
    }

    public function render()
    {
        $this->authorizeAdmin();
        $company = Company::findOrFail($this->companyId);

        return view('livewire.admin.company-show', [
            'company' => $company,
            // Membres = comptes de la plateforme (nom, e-mail de connexion, rôle) ; aucune donnée métier de l'entreprise.
            'members' => $company->users()->orderBy('users.name')->get(),
            'logs' => AuditLog::with('actor:id,name')->where('company_id', $company->id)->latest('id')->limit(20)->get(),
        ]);
    }

    private function change(callable $fn, string $success): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Un motif est obligatoire.']);
        try {
            $fn(Company::findOrFail($this->companyId));
        } catch (InvalidArgumentException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }
        $this->reset('reason');
        session()->flash('status', $success);
    }
}
