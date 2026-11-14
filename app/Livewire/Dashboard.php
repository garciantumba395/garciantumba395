<?php

namespace App\Livewire;

use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public function render(CurrentCompany $current)
    {
        return view('livewire.dashboard', ['company' => $current->company(), 'role' => $current->role()]);
    }
}
