<?php

namespace App\Jobs;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Models\Campaign;
use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Fige les destinataires puis met en file un job par destinataire. Rejouable : aucune écriture en double. */
class PrepareCampaign implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public int $campaignId) {}

    public function uniqueId(): string
    {
        return 'campaign-prepare-'.$this->campaignId;
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(CampaignService $service, CurrentCompany $current): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);
        if (! $campaign || $campaign->status !== CampaignStatus::Preparing) {
            return; // déjà préparée, annulée ou en pause : rien à faire
        }

        // Le contexte d'entreprise est fixé EXPLICITEMENT (un worker n'a pas d'utilisateur connecté), puis restauré.
        $current->runAs(Company::findOrFail($campaign->company_id), function () use ($service, $campaign) {
            $service->snapshotRecipients($campaign);
            $service->dispatchPending($campaign->id);
            $service->markSending($campaign->id);
            $service->completeIfDone($campaign->id); // cas où tout est déjà traité (queue synchrone) ou liste vide
        });
    }
}
