<?php

namespace App\Livewire\Companies;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contacts\PhoneNormalizer;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Paramètres de l\'entreprise')]
class Settings extends Component
{
    public string $name = '';

    public string $country = 'CD';

    public function mount(CurrentCompany $current): void
    {
        abort_unless($current->role()?->canManageCompany(), 403);
        $this->name = $current->company()->name;
        $this->country = $current->company()->country;
    }

    public function save(CurrentCompany $current, AuditLogger $audit): void
    {
        // Revérifié à chaque action : l'état Livewire vient du client.
        abort_unless($current->role()?->canManageCompany(), 403);
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', fn ($a, $v, $fail) => PhoneNormalizer::isSupportedCountry($v) || $fail('Pays non supporté.')],
        ]);

        $company = $current->company();
        $before = ['name' => $company->name, 'country' => $company->country];
        $company->update(['name' => $this->name, 'country' => strtoupper($this->country)]);
        $changes = [];
        foreach (['name', 'country'] as $field) {
            if ($company->{$field} !== $before[$field]) {
                $changes[$field] = ['from' => $before[$field], 'to' => $company->{$field}];
            }
        }
        if ($changes !== []) {
            $audit->record('company.updated', $company, $company, $changes);
        }
        session()->flash('status', 'Entreprise mise à jour.');
    }

    public function render()
    {
        return view('livewire.companies.settings', ['countries' => PhoneNormalizer::countries()]);
    }
}
