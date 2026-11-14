<?php

namespace App\Livewire\Companies;

use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Paramètres de l\'entreprise')]
class Settings extends Component
{
    public string $name = '';

    public function mount(CurrentCompany $current): void
    {
        abort_unless($current->role()?->canManageCompany(), 403);
        $this->name = $current->company()->name;
    }

    public function save(CurrentCompany $current): void
    {
        // Revérifié à chaque action : l'état Livewire vient du client.
        abort_unless($current->role()?->canManageCompany(), 403);
        $this->validate(['name' => ['required', 'string', 'max:255']]);

        $current->company()->update(['name' => $this->name]);
        session()->flash('status', 'Entreprise mise à jour.');
    }

    public function render()
    {
        return view('livewire.companies.settings');
    }
}
