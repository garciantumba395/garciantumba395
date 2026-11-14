<?php

namespace App\Domain\Sms\Webhooks;

use App\Domain\Consent\ConsentService;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsStatus;
use App\Models\Company;
use App\Models\SmsMessage;
use App\Support\Tenancy\CurrentCompany;

/**
 * Applique un événement NORMALISÉ (déjà authentifié et dédupliqué).
 *
 * Règle de désinscription par mot-clé (ambiguïté assumée) : un SMS entrant ne désigne pas l'entreprise visée (un numéro peut
 * avoir été contacté par plusieurs entreprises). Le numéro est donc désinscrit dans CHAQUE entreprise qui lui a envoyé un message
 * RÉEL (simulated = false, statut envoyé/délivré/résultat inconnu) dans les `sms.inbound.window_days` derniers jours.
 * Une entreprise sans envoi récent n'est pas touchée. Les messages simulés ne désinscrivent jamais personne.
 * Le texte libre reçu n'est jamais stocké (donnée personnelle) : seul un mot-clé reconnu a un effet.
 */
class SmsWebhookProcessor
{
    public function __construct(
        private SmsDispatcher $dispatcher,
        private ConsentService $consent,
        private CurrentCompany $current,
    ) {}

    /** @return string issue : applied, optout ou ignored */
    public function process(string $provider, SmsWebhookEvent $event): string
    {
        return match ($event->type) {
            SmsWebhookEventType::DeliveryReport => $this->deliveryReport($provider, $event),
            SmsWebhookEventType::Inbound => $this->inbound($provider, $event),
        };
    }

    private function deliveryReport(string $provider, SmsWebhookEvent $event): string
    {
        $message = $this->dispatcher->recordDeliveryReport($provider, (string) $event->providerMessageId, $event->status, $event->errorCode);

        return $message === null ? 'ignored' : 'applied';
    }

    private function inbound(string $provider, SmsWebhookEvent $event): string
    {
        if (! InboundKeyword::matches((string) $event->text, (array) config('sms.inbound.optout_keywords', []))) {
            return 'ignored';
        }

        $since = now()->subDays((int) config('sms.inbound.window_days'));
        $companyIds = SmsMessage::withoutGlobalScopes()
            ->where('phone', $event->from)
            ->where('simulated', false)
            ->whereIn('status', [SmsStatus::Sent->value, SmsStatus::Delivered->value, SmsStatus::Unknown->value])
            ->where('created_at', '>=', $since)
            ->distinct()->pluck('company_id');

        $count = 0;
        foreach (Company::whereIn('id', $companyIds)->get() as $company) {
            $this->current->runAs($company, fn () => $this->consent->optOut((string) $event->from, 'sms_keyword', 'Mot-clé reçu par SMS ('.$provider.')'));
            $count++;
        }

        return $count > 0 ? 'optout' : 'ignored';
    }
}
