<?php

namespace App\Livewire\Companies;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Switcher extends Component
{
    public function switch(int $companyId, CurrentCompany $current)
    {
        // Refus silencieux si l'utilisateur n'est pas membre : l'ID vient du client, il n'est jamais cru.
        abort_unless($current->switchTo(Auth::user(), $companyId), 403);

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render(CurrentCompany $current)
    {
        return view('livewire.companies.switcher', [
            'companies' => Auth::user()->companies()->where('companies.is_active', true)->orderBy('name')->get(),
            'currentId' => $current->id(),
        ]);
    }
}
