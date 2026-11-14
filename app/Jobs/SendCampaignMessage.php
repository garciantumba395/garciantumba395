<?php

namespace App\Jobs;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Legal\MessageComposer;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsSendingDisabledException;
use App\Domain\Sms\SmsStatus;
use App\Domain\Sms\UnknownSmsProviderException;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Company;
use App\Models\Contact;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie UN message de campagne. Rejouable sans risque : la clé d'idempotence est déterministe
 * (campaign:{id}:contact:{id}[:r{n}]) donc un second passage ne renvoie ni ne redébite jamais.
 * Le consentement et la désinscription sont revérifiés par SmsDispatcher au moment de l'envoi.
 */
class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Motifs de blocage TEMPORAIRES : la campagne se met en pause au lieu de perdre les destinataires. */
    private const TRANSIENT = ['insufficient_credits', 'daily_limit', 'suspended'];

    public int $tries = 5;

    public function __construct(public int $campaignMessageId) {}

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(SmsDispatcher $dispatcher, CurrentCompany $current, CampaignService $service, MessageComposer $composer, SmsManager $manager): void
    {
        $cm = CampaignMessage::withoutGlobalScopes()->find($this->campaignMessageId);
        if (! $cm || $cm->processed_at !== null) {
            return; // déjà traité (rejeu)
        }
        $campaign = Campaign::withoutGlobalScopes()->find($cm->campaign_id);
        if (! $campaign || ! $campaign->status->isActive()) {
            return; // en pause, annulée ou terminée : on n'envoie plus rien
        }

        $release = $current->runAs(Company::findOrFail($cm->company_id), fn () => $this->process($cm, $campaign, $dispatcher, $service, $composer, $manager));
        if ($release) {
            $this->release(30); // un autre worker traite ce message : on revérifiera plus tard
        }
    }

    /** @return bool true si le job doit être remis en file */
    private function process(CampaignMessage $cm, Campaign $campaign, SmsDispatcher $dispatcher, CampaignService $service, MessageComposer $composer, SmsManager $manager): bool
    {
        $company = Company::findOrFail($cm->company_id);

        // Art. 66 du Code du numérique : un envoi RÉEL de publicité exige l'identité de l'expéditeur. La simulation n'est pas concernée.
        if (config('legal.enforce') && $manager->isLiveMode() && ! $company->hasCompleteLegalIdentity()) {
            $service->pause($campaign, 'legal_identity_incomplete', 'Identité légale de l\'expéditeur incomplète (art. 66) : complétez-la dans Paramètres, puis reprenez la campagne.');

            return false;
        }

        $contact = Contact::find($cm->contact_id); // requête scopée par l'entreprise du message
        if (! $contact) {
            $cm->update(['processed_at' => now(), 'note' => 'contact_deleted']);
            $service->completeIfDone($campaign->id);

            return false;
        }

        $key = "campaign:{$campaign->id}:contact:{$contact->id}".($cm->attempt > 0 ? ":r{$cm->attempt}" : '');

        try {
            $message = $dispatcher->dispatch($contact, $composer->compose($campaign->body, $contact, $company), $key, null, $campaign->created_by);
        } catch (SmsSendingDisabledException|UnknownSmsProviderException $e) {
            // Rien n'a été écrit ni envoyé : on met la campagne en pause, aucun destinataire n'est perdu.
            $service->pause($campaign, 'provider_unavailable', $e->getMessage());

            return false;
        }

        if (in_array($message->status, [SmsStatus::Pending, SmsStatus::Sending], true)) {
            return true;
        }

        if ($message->status === SmsStatus::Blocked && in_array($message->blocked_reason, self::TRANSIENT, true)) {
            // Nouvelle tentative possible après reprise (nouvelle clé) ; la ligne bloquée reste au journal.
            $cm->update(['attempt' => $cm->attempt + 1]);
            $service->pause($campaign, $message->blocked_reason, $this->pauseMessage($message->blocked_reason));

            return false;
        }

        $cm->update(['sms_message_id' => $message->id, 'processed_at' => now()]);
        $service->completeIfDone($campaign->id);

        return false;
    }

    private function pauseMessage(string $reason): string
    {
        return match ($reason) {
            'insufficient_credits' => 'Crédits insuffisants : ajoutez des crédits puis reprenez la campagne.',
            'daily_limit' => 'Limite journalière d\'envois atteinte : reprise automatique le jour suivant.',
            'suspended' => 'Envoi réel suspendu pour votre entreprise.',
        };
    }
}
