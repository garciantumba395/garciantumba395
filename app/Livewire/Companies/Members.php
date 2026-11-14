<?php

namespace App\Livewire\Companies;

use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Membres')]
class Members extends Component
{
    public function render(CurrentCompany $current)
    {
        return view('livewire.companies.members', [
            'members' => $current->company()->users()->orderBy('users.name')->get(),
        ]);
    }
}
