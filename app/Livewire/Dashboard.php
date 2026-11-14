<?php

namespace App\Livewire;

use App\Domain\Credits\CreditService;
use App\Domain\Credits\CreditUnits;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Suppression;
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
        return view('livewire.dashboard', ['company' => $current->company(), 'role' => $current->role(), 'contacts' => Contact::count(), 'groups' => ContactGroup::count(), 'balance' => CreditUnits::format(app(CreditService::class)->balance($current->company())), 'sendable' => Contact::sendable()->count(), 'suppressed' => Suppression::active()->count()]);
    }
}
