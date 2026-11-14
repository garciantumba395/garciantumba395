<?php

namespace App\Livewire\Credits;

use App\Domain\Credits\CreditService;
use App\Domain\Credits\CreditUnits;
use App\Models\CreditLedgerEntry;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Crédits')]
class Index extends Component
{
    use WithPagination;

    public function render(CurrentCompany $current, CreditService $credits)
    {
        $balance = $credits->balance($current->company());

        return view('livewire.credits.index', [
            'balance' => $balance,
            'balanceText' => CreditUnits::format($balance),
            // Estimation indicative au tarif par défaut ; le coût réel dépend du pays/opérateur et de la longueur du message.
            'estimatedSms' => intdiv($balance, max(1, (int) config('credits.default_units_per_segment'))),
            'entries' => CreditLedgerEntry::latest('id')->paginate(25),
        ]);
    }
}
