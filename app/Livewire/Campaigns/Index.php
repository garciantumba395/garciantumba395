<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\CampaignStatus;
use App\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Campagnes')]
class Index extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        $campaign = Campaign::findOrFail($id); // scopé : 404 hors entreprise
        abort_unless($campaign->status === CampaignStatus::Draft, 403); // seul un brouillon se supprime
        $campaign->delete();
        session()->flash('status', 'Brouillon supprimé.');
    }

    public function render()
    {
        return view('livewire.campaigns.index', ['campaigns' => Campaign::latest('id')->paginate(20)]);
    }
}
