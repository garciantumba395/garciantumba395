<?php

namespace App\Livewire\Campaigns;

use App\Domain\Audit\AuditLogger;
use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Sms\SmsManager;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactGroup;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Campagne')]
class Show extends Component
{
    #[Locked]
    public int $campaignId;

    public function mount(int $campaign): void
    {
        $this->campaignId = Campaign::findOrFail($campaign)->id; // 404 hors entreprise
    }

    public function launch(CampaignService $service, CurrentCompany $current, AuditLogger $audit): void
    {
        $this->authorizeManage($current);
        $campaign = Campaign::findOrFail($this->campaignId);
        try {
            $service->launch($campaign, $campaign->scheduled_at);
        } catch (InvalidArgumentException $e) {
            $this->addError('action', $e->getMessage());

            return;
        }
        $audit->record('campaign.launched', $campaign->company_id, $campaign, ['scheduled' => (bool) ($campaign->scheduled_at && $campaign->scheduled_at->isFuture())]);
        session()->flash('status', $campaign->scheduled_at && $campaign->scheduled_at->isFuture() ? 'Campagne programmée.' : 'Campagne lancée.');
    }

    public function pause(CampaignService $service, CurrentCompany $current, AuditLogger $audit): void
    {
        $this->authorizeManage($current);
        $campaign = Campaign::findOrFail($this->campaignId);
        if ($service->pause($campaign, 'manual', 'Mise en pause manuelle.')) {
            $audit->record('campaign.paused', $campaign->company_id, $campaign, ['code' => 'manual']);
        }
    }

    public function resume(CampaignService $service, CurrentCompany $current, AuditLogger $audit): void
    {
        $this->authorizeManage($current);
        $campaign = Campaign::findOrFail($this->campaignId);
        if ($service->resume($campaign)) {
            $audit->record('campaign.resumed', $campaign->company_id, $campaign, ['code' => 'manual']);
        }
    }

    public function cancel(CampaignService $service, CurrentCompany $current, AuditLogger $audit): void
    {
        $this->authorizeManage($current);
        $campaign = Campaign::findOrFail($this->campaignId);
        if ($service->cancel($campaign)) {
            $audit->record('campaign.cancelled', $campaign->company_id, $campaign);
        }
        session()->flash('status', 'Campagne annulée : plus aucun message ne sera envoyé.');
    }

    public function render(CampaignService $service, SmsManager $manager, CurrentCompany $current)
    {
        $campaign = Campaign::findOrFail($this->campaignId);

        return view('livewire.campaigns.show', [
            'campaign' => $campaign,
            'group' => $campaign->contact_group_id ? ContactGroup::find($campaign->contact_group_id) : null,
            'estimate' => in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true) ? $service->estimate($campaign) : null,
            'stats' => $this->stats($campaign),
            ...$this->reportPeriod($campaign),
            'live' => $manager->isLiveMode(),
            'canManage' => (bool) $current->role()?->canManageCompany(),
        ]);
    }

    /** Période du lien vers le rapport : de la création de la campagne à aujourd'hui (fuseau de l'application), bornée à la période maximale. */
    private function reportPeriod(Campaign $campaign): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $from = CarbonImmutable::parse($campaign->created_at->format('Y-m-d'), config('app.timezone'))->startOfDay();
        $earliest = $today->subDays((int) config('reports.max_range_days') - 1);

        return ['reportFrom' => max($from, $earliest)->format('Y-m-d'), 'reportTo' => $today->format('Y-m-d')];
    }

    /** Compteurs issus UNIQUEMENT des données réelles de la base (messages du journal). */
    private function stats(Campaign $campaign): array
    {
        $base = CampaignMessage::where('campaign_id', $campaign->id);
        $byStatus = DB::table('campaign_messages as cm')->join('sms_messages as sm', 'sm.id', '=', 'cm.sms_message_id')
            ->where('cm.campaign_id', $campaign->id)->where('cm.company_id', $campaign->company_id)
            ->selectRaw('sm.status as status, sm.blocked_reason as reason, count(*) as n')->groupBy('sm.status', 'sm.blocked_reason')->get();

        $count = fn (string $status) => (int) $byStatus->where('status', $status)->sum('n');

        return [
            'recipients' => $campaign->recipient_count,
            'pending' => (clone $base)->whereNull('processed_at')->count(),
            'sent' => $count('sent'), 'delivered' => $count('delivered'), 'failed' => $count('failed'), 'unknown' => $count('unknown'),
            'blocked_consent' => (int) $byStatus->where('status', 'blocked')->whereIn('reason', ['no_consent', 'suppressed'])->sum('n'),
            'skipped' => (clone $base)->whereNotNull('note')->count(),
            'simulated' => DB::table('campaign_messages as cm')->join('sms_messages as sm', 'sm.id', '=', 'cm.sms_message_id')
                ->where('cm.campaign_id', $campaign->id)->where('cm.company_id', $campaign->company_id)->where('sm.simulated', true)->exists(),
        ];
    }

    private function authorizeManage(CurrentCompany $current): void
    {
        // Lancer, mettre en pause, reprendre, annuler : owner/admin uniquement (vérifié à chaque action).
        abort_unless($current->role()?->canManageCompany(), 403);
    }
}
