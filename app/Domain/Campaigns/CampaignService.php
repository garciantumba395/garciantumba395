<?php

namespace App\Domain\Campaigns;

use App\Domain\Audit\AuditLogger;
use App\Domain\Credits\CostCalculator;
use App\Domain\Credits\CreditService;
use App\Jobs\PrepareCampaign;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cycle de vie d'une campagne. Toutes les transitions sont des mises à jour CONDITIONNELLES (compare-and-set) :
 * deux acteurs concurrents (utilisateur, planificateur, jobs) ne peuvent pas se contredire ni lancer deux fois.
 *
 *   draft → scheduled → preparing → sending → completed
 *                         ↘ paused ↔ sending ; tout état non terminal → cancelled
 */
class CampaignService
{
    public function __construct(private CostCalculator $costs, private CreditService $credits, private CurrentCompany $current) {}

    /** Estimation indicative avant lancement : destinataires ciblables MAINTENANT et coût. */
    public function estimate(Campaign $campaign): array
    {
        $rates = $this->costs->loadRates();
        $recipients = 0;
        $units = 0;
        $this->targetQuery($campaign)->sendable()->select('contacts.id', 'contacts.phone')
            ->chunkById(1000, function ($contacts) use (&$recipients, &$units, $campaign, $rates) {
                foreach ($contacts as $c) {
                    $recipients++;
                    $units += $this->costs->forMessage($c->phone, $campaign->body, $rates)->totalUnits();
                }
            }, 'contacts.id', 'id');

        $excluded = max(0, $this->targetQuery($campaign)->count() - $recipients);
        $balance = $this->credits->balance($campaign->company_id);

        return ['recipients' => $recipients, 'excluded' => $excluded, 'cost_units' => $units, 'balance_units' => $balance, 'enough' => $units <= $balance];
    }

    /** Programme la campagne (date vide = immédiat). Réservé aux rôles autorisés par l'appelant. */
    public function launch(Campaign $campaign, ?\DateTimeInterface $at = null): void
    {
        if (trim($campaign->body) === '' || mb_strlen($campaign->body) > config('sms.max_body_length')) {
            throw new InvalidArgumentException('Message vide ou trop long.');
        }
        if ($this->estimate($campaign)['recipients'] === 0) {
            throw new InvalidArgumentException('Aucun destinataire ciblable (consentement valide et non désinscrit requis).');
        }

        $ok = Campaign::whereKey($campaign->id)->where('status', CampaignStatus::Draft->value)
            ->update(['status' => CampaignStatus::Scheduled->value, 'scheduled_at' => $at ?? now()]);
        if ($ok === 0) {
            throw new InvalidArgumentException('Seul un brouillon peut être lancé.');
        }

        $this->dispatchDue(); // démarre tout de suite si l'heure est atteinte
    }

    /** Démarre les campagnes programmées dont l'heure est atteinte (planificateur, chaque minute, et au lancement). */
    public function dispatchDue(): int
    {
        $started = 0;
        $ids = Campaign::withoutGlobalScopes()->where('status', CampaignStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            // Une entreprise désactivée par l'administration ne démarre plus de campagne (étape 8).
            ->whereIn('company_id', fn ($q) => $q->select('id')->from('companies')->where('is_active', true))
            ->pluck('id');

        foreach ($ids as $id) {
            if ($this->claimForPreparation($id)) {
                PrepareCampaign::dispatch($id)->onQueue(config('sms.queue'));
                $started++;
            }
        }

        return $started;
    }

    /** Compare-and-set scheduled → preparing : un seul des acteurs concurrents (planificateur, lancement) obtient true. */
    public function claimForPreparation(int $campaignId): bool
    {
        return Campaign::withoutGlobalScopes()->whereKey($campaignId)->where('status', CampaignStatus::Scheduled->value)
            ->update(['status' => CampaignStatus::Preparing->value, 'started_at' => now()]) === 1;
    }

    /** Reprend automatiquement les campagnes mises en pause par la limite journalière, à partir du jour suivant. */
    public function resumeDailyLimited(): int
    {
        $n = 0;
        $ids = Campaign::withoutGlobalScopes()->where('status', CampaignStatus::Paused->value)
            ->where('pause_code', 'daily_limit')->where('paused_at', '<', now()->startOfDay())->pluck('id');
        foreach ($ids as $id) {
            $campaign = Campaign::withoutGlobalScopes()->findOrFail($id);
            if ($this->resume($campaign)) {
                $n++;
                app(AuditLogger::class)->recordSystem('campaign.resumed', $campaign->company_id, $campaign, ['code' => 'daily_limit_reset']);
            }
        }

        return $n;
    }

    public function pause(Campaign $campaign, string $code = 'manual', ?string $message = null): bool
    {
        $ok = Campaign::withoutGlobalScopes()->whereKey($campaign->id)
            ->whereIn('status', [CampaignStatus::Preparing->value, CampaignStatus::Sending->value])
            ->update(['status' => CampaignStatus::Paused->value, 'pause_code' => $code, 'pause_message' => $message ? mb_substr($message, 0, 255) : null, 'paused_at' => now()]) === 1;
        // Pause manuelle : auditée par l'action de l'utilisateur (Campaigns\Show). Pause automatique : acteur « système ».
        if ($ok && $code !== 'manual') {
            app(AuditLogger::class)->recordSystem('campaign.paused', $campaign->company_id, $campaign, ['code' => $code]);
        }

        return $ok;
    }

    /** Reprend une campagne en pause : seuls les destinataires non traités sont relancés (clés d'idempotence : aucun doublon). */
    public function resume(Campaign $campaign): bool
    {
        $ok = Campaign::withoutGlobalScopes()->whereKey($campaign->id)->where('status', CampaignStatus::Paused->value)
            ->update(['status' => CampaignStatus::Sending->value, 'pause_code' => null, 'pause_message' => null, 'paused_at' => null]);
        if ($ok === 1) {
            $this->dispatchPending($campaign->id);
        }

        return $ok === 1;
    }

    public function cancel(Campaign $campaign): bool
    {
        return Campaign::withoutGlobalScopes()->whereKey($campaign->id)
            ->whereIn('status', [CampaignStatus::Scheduled->value, CampaignStatus::Preparing->value, CampaignStatus::Sending->value, CampaignStatus::Paused->value])
            ->update(['status' => CampaignStatus::Cancelled->value, 'cancelled_at' => now()]) === 1;
    }

    /** Fige les destinataires (contexte d'entreprise requis). Idempotent : INSERT … ON CONFLICT DO NOTHING. */
    public function snapshotRecipients(Campaign $campaign): void
    {
        $this->targetQuery($campaign)->sendable()->select('contacts.id', 'contacts.phone')
            ->chunkById(1000, function ($contacts) use ($campaign) {
                if (! Campaign::withoutGlobalScopes()->whereKey($campaign->id)->where('status', CampaignStatus::Preparing->value)->exists()) {
                    return false; // annulée ou mise en pause pendant la préparation
                }
                DB::table('campaign_messages')->insertOrIgnore($contacts->map(fn ($c) => [
                    'company_id' => $campaign->company_id, 'campaign_id' => $campaign->id, 'contact_id' => $c->id,
                    'phone' => $c->phone, 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }, 'contacts.id', 'id');

        $recipients = CampaignMessage::withoutGlobalScopes()->where('campaign_id', $campaign->id)->count();
        $total = $this->targetQuery($campaign)->count();
        Campaign::withoutGlobalScopes()->whereKey($campaign->id)->update(['recipient_count' => $recipients, 'excluded_count' => max(0, $total - $recipients)]);
    }

    /** Met en file un job par destinataire non traité (rejouable sans risque). */
    public function dispatchPending(int $campaignId): void
    {
        CampaignMessage::withoutGlobalScopes()->where('campaign_id', $campaignId)->whereNull('processed_at')
            ->select('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    SendCampaignMessage::dispatch($row->id)->onQueue(config('sms.queue'));
                }
            });
    }

    /** preparing → sending une fois les jobs en file. */
    public function markSending(int $campaignId): void
    {
        Campaign::withoutGlobalScopes()->whereKey($campaignId)->where('status', CampaignStatus::Preparing->value)
            ->update(['status' => CampaignStatus::Sending->value]);
    }

    /** Termine la campagne quand plus aucun destinataire n'est en attente. */
    public function completeIfDone(int $campaignId): void
    {
        $pending = CampaignMessage::withoutGlobalScopes()->where('campaign_id', $campaignId)->whereNull('processed_at')->exists();
        if (! $pending) {
            Campaign::withoutGlobalScopes()->whereKey($campaignId)->where('status', CampaignStatus::Sending->value)
                ->update(['status' => CampaignStatus::Completed->value, 'completed_at' => now()]);
        }
    }

    private function targetQuery(Campaign $campaign)
    {
        // Requête scopée par l'entreprise active (contexte de la requête ou du job).
        return Contact::query()->when($campaign->contact_group_id, fn ($q, $g) => $q->whereExists(
            fn ($x) => $x->from('contact_group_contact')->whereColumn('contact_group_contact.contact_id', 'contacts.id')
                ->whereColumn('contact_group_contact.company_id', 'contacts.company_id')->where('contact_group_contact.contact_group_id', $g)
        ));
    }
}
