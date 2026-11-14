<?php

namespace App\Livewire\Companies;

use App\Domain\Audit\AuditLogger;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Identité légale de l'expéditeur de publicité (art. 66 du Code du numérique) : nom, adresse, e-mail obligatoires pour tout envoi réel. */
#[Layout('layouts.app')]
#[Title('Identité légale de l\'expéditeur')]
class LegalIdentity extends Component
{
    public string $legalName = '';

    public string $legalAddress = '';

    public string $legalEmail = '';

    public string $legalPhone = '';

    public string $legalRccm = '';

    public string $legalTaxId = '';

    public string $legalAct = '';

    public function mount(CurrentCompany $current): void
    {
        abort_unless($current->role()?->canManageCompany(), 403);
        $c = $current->company();
        $this->legalName = (string) $c->legal_name;
        $this->legalAddress = (string) $c->legal_address;
        $this->legalEmail = (string) $c->legal_email;
        $this->legalPhone = (string) $c->legal_phone;
        $this->legalRccm = (string) $c->legal_rccm;
        $this->legalTaxId = (string) $c->legal_tax_id;
        $this->legalAct = (string) $c->legal_act;
    }

    public function save(CurrentCompany $current, AuditLogger $audit): void
    {
        abort_unless($current->role()?->canManageCompany(), 403); // revérifié : l'état Livewire vient du client
        $data = $this->validate([
            'legalName' => ['required', 'string', 'max:255'],
            'legalAddress' => ['required', 'string', 'max:500'],
            'legalEmail' => ['required', 'email', 'max:255'],
            'legalPhone' => ['nullable', 'string', 'max:30'],
            'legalRccm' => ['nullable', 'string', 'max:60'],
            'legalTaxId' => ['nullable', 'string', 'max:60'],
            'legalAct' => ['nullable', 'string', 'max:255'],
        ]);

        $company = $current->company();
        $company->update([
            'legal_name' => trim($data['legalName']), 'legal_address' => trim($data['legalAddress']), 'legal_email' => trim($data['legalEmail']),
            'legal_phone' => trim((string) $data['legalPhone']) ?: null, 'legal_rccm' => trim((string) $data['legalRccm']) ?: null,
            'legal_tax_id' => trim((string) $data['legalTaxId']) ?: null, 'legal_act' => trim((string) $data['legalAct']) ?: null,
        ]);
        $audit->record('company.legal_identity_updated', $company, $company, ['complete' => $company->hasCompleteLegalIdentity()]); // aucune valeur dans l'audit

        session()->flash('status', 'Identité légale enregistrée.');
    }

    public function render(CurrentCompany $current)
    {
        return view('livewire.companies.legal-identity', ['complete' => $current->company()->hasCompleteLegalIdentity()]);
    }
}
