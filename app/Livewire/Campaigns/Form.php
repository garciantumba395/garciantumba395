<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Credits\SegmentCalculator;
use App\Domain\Legal\MessageComposer;
use App\Models\Campaign;
use App\Models\ContactGroup;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Campagne')]
class Form extends Component
{
    #[Locked]
    public ?int $campaignId = null;

    public string $name = '';

    public string $body = '';

    public ?int $groupId = null;

    public string $scheduledAt = '';

    public function mount(?int $campaign = null): void
    {
        if ($campaign !== null) {
            $c = Campaign::findOrFail($campaign); // 404 hors entreprise
            abort_unless($c->status === CampaignStatus::Draft, 403); // seul un brouillon est modifiable
            $this->campaignId = $c->id;
            $this->name = $c->name;
            $this->body = $c->body;
            $this->groupId = $c->contact_group_id;
            $this->scheduledAt = $c->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') ?? '';
        }
    }

    public function save()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:'.config('sms.max_body_length')],
            'groupId' => ['nullable', 'integer'],
            'scheduledAt' => ['nullable', 'date'],
        ]);

        if ($this->groupId && ! ContactGroup::whereKey($this->groupId)->exists()) { // scopé : groupe d'une autre entreprise refusé
            $this->addError('groupId', 'Groupe introuvable.');

            return;
        }
        if ($this->scheduledAt !== '' && Carbon::parse($this->scheduledAt, config('app.timezone'))->isPast()) {
            $this->addError('scheduledAt', 'La date de programmation doit être dans le futur (laissez vide pour un envoi immédiat).');

            return;
        }

        $attrs = [
            'name' => $data['name'], 'body' => $data['body'], 'contact_group_id' => $this->groupId ?: null,
            // Laravel enregistre et relit les dates dans le fuseau de l'application : surtout pas de conversion en UTC ici.
            'scheduled_at' => $this->scheduledAt !== '' ? Carbon::parse($this->scheduledAt, config('app.timezone')) : null,
        ];

        if ($this->campaignId) {
            $campaign = Campaign::whereKey($this->campaignId)->where('status', CampaignStatus::Draft->value)->firstOrFail();
            $campaign->update($attrs);
        } else {
            $campaign = new Campaign($attrs);
            $campaign->created_by = Auth::id();
            $campaign->save();
        }

        return $this->redirectRoute('campaigns.show', $campaign->id, navigate: false);
    }

    public function render(SegmentCalculator $segments, MessageComposer $composer, CurrentCompany $current)
    {
        $company = $current->company();

        return view('livewire.campaigns.form', [
            'groups' => ContactGroup::orderBy('name')->get(),
            'analysis' => $segments->analyze($composer->composeForEstimate($this->body, $company)),
            'footerLength' => $composer->footerLength($company),
            'enforce' => (bool) config('legal.enforce'),
            'companyName' => $company->senderName(),
        ]);
    }
}
