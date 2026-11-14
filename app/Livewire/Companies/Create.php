<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Nouvelle entreprise')]
class Create extends Component
{
    public string $name = '';

    public function save(CurrentCompany $current)
    {
        $this->validate(['name' => ['required', 'string', 'max:255']]);

        $company = Company::createFor(Auth::user(), $this->name);
        $current->switchTo(Auth::user(), $company->id);

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.companies.create');
    }
}
