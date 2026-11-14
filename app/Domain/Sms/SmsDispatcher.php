<?php

namespace App\Domain\Sms;

use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
use App\Domain\Credits\CostCalculator;
use App\Domain\Credits\CreditService;
use App\Domain\Credits\InsufficientCreditsException;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Point d'entrée UNIQUE pour envoyer un SMS à un contact. Garanties :
 *  1. fournisseur résolu d'abord : verrou d'activation (rien n'est créé ni envoyé si un fournisseur réel n'est pas activé) ;
 *  2. consentement + désinscription revérifiés au moment de l'envoi (SendEligibility) ;
 *  3. idempotence : une clé d'idempotence = au plus UN envoi (contrainte UNIQUE en base + prise en charge atomique) ;
 *  3bis. envoi RÉEL seulement : garde-fous anti-abus (SendingPolicy : suspension, limite journalière) ;
 *  4. débit des crédits AVANT l'appel au fournisseur (clé « sms:{id} », donc jamais deux fois) ; solde insuffisant = aucun envoi ;
 *  5. refus définitif du fournisseur = remboursement (une seule fois) ; une erreur de transport laisse le statut « unknown »,
 *     SANS remboursement et SANS renvoi automatique (le message a peut-être été envoyé : réconciliation manuelle).
 */
class SmsDispatcher
{
    public function __construct(
        private SmsManager $manager,
        private SendEligibility $eligibility,
        private CurrentCompany $current,
        private CreditService $credits,
        private CostCalculator $costs,
        private SendingPolicy $policy,
    ) {}

    public function dispatch(Contact $contact, string $body, string $idempotencyKey, ?string $senderId = null, ?int $userId = null): SmsMessage
    {
        $this->validate($body, $idempotencyKey);

        $companyId = $this->current->id() ?? throw new LogicException('Aucune entreprise active.');
        if ($contact->company_id !== $companyId) {
            throw new LogicException('Le contact n\'appartient pas à l\'entreprise active.');
        }

        // 1. Verrou d'activation : lève SmsSendingDisabledException AVANT toute écriture.
        $provider = $this->manager->provider();

        $cost = $this->costs->forMessage($contact->phone, $body);

        // 3. Idempotence : insertion ignorée si la clé existe déjà (pas d'erreur SQL, sûr en concurrence).
        $inserted = DB::table('sms_messages')->insertOrIgnore([
            'company_id' => $companyId,
            'contact_id' => $contact->id,
            'idempotency_key' => $idempotencyKey,
            'phone' => $contact->phone,
            'body' => $body,
            'sender_id' => $senderId,
            'status' => SmsStatus::Pending->value,
            'provider' => $provider->name(),
            'simulated' => ! $provider->isLive(),
            'segments' => $cost->segments,
            'encoding' => $cost->encoding,
            'cost_units' => $cost->totalUnits(),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = SmsMessage::where('idempotency_key', $idempotencyKey)->firstOrFail();
        if ($inserted === 0) {
            return $message; // doublon : on renvoie l'existant, rien n'est renvoyé au fournisseur
        }

        // 2. Consentement et désinscription au moment de l'envoi.
        $eligible = $this->eligibility->for($contact);
        if ($eligible !== Eligibility::Eligible) {
            $message->update(['status' => SmsStatus::Blocked, 'blocked_reason' => $eligible->value]);

            return $message;
        }

        // Garde-fous anti-abus : uniquement pour un fournisseur réel (la simulation n'est jamais limitée).
        if ($provider->isLive() && ($reason = $this->policy->blockReason($companyId, $message->id))) {
            $message->update(['status' => SmsStatus::Blocked, 'blocked_reason' => $reason]);

            return $message;
        }

        // Prise en charge atomique : un seul appelant peut passer de pending à sending.
        $claimed = SmsMessage::whereKey($message->id)->where('status', SmsStatus::Pending->value)
            ->update(['status' => SmsStatus::Sending->value, 'attempts' => DB::raw('attempts + 1'), 'sending_at' => now()]);
        if ($claimed === 0) {
            return $message->fresh();
        }

        // 4. Débit idempotent avant l'envoi : sans crédits suffisants, rien n'est envoyé.
        try {
            $debit = $this->credits->debit($companyId, $cost->totalUnits(), 'sms:'.$message->id, $message->id, ! $provider->isLive(), $userId);
        } catch (InsufficientCreditsException) {
            $message->update(['status' => SmsStatus::Blocked, 'blocked_reason' => 'insufficient_credits']);

            return $message;
        }

        try {
            $result = $provider->send(new SmsRequest($contact->phone, $body, 'm'.$message->id, $senderId));
        } catch (Throwable $e) {
            // 4. Résultat inconnu : le message a pu partir. Pas de renvoi automatique.
            $message->update(['status' => SmsStatus::Unknown, 'error_code' => 'TRANSPORT_ERROR', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
            $this->evaluateIfLive($provider, $companyId);

            return $message;
        }

        if ($result->accepted) {
            $message->update(['status' => SmsStatus::Sent, 'provider_message_id' => $result->providerMessageId, 'sent_at' => now()]);
        } else {
            // 5. Refus définitif : le message n'est pas parti, on rembourse (une seule fois, clé dérivée du débit).
            $this->credits->refund($debit, 'Refus du fournisseur : '.$result->errorCode);
            $message->update(['status' => SmsStatus::Failed, 'error_code' => $result->errorCode, 'error_message' => mb_substr((string) $result->errorMessage, 0, 500)]);
        }

        $this->evaluateIfLive($provider, $companyId);

        return $message;
    }

    private function evaluateIfLive(SmsProvider $provider, int $companyId): void
    {
        if ($provider->isLive()) {
            $this->policy->evaluate($companyId); // D12 : validation / suspension automatiques après les envois réels
        }
    }

    /**
     * Applique un accusé de réception du fournisseur (appelé plus tard par le webhook du fournisseur réel).
     * Ne fait jamais reculer un statut. Recherche hors scope entreprise : un webhook n'a pas de contexte d'entreprise.
     */
    public function recordDeliveryReport(string $provider, string $providerMessageId, SmsStatus $status, ?string $errorCode = null): ?SmsMessage
    {
        if (! in_array($status, [SmsStatus::Delivered, SmsStatus::Failed], true)) {
            throw new InvalidArgumentException('Un accusé de réception est « délivré » ou « échec ».');
        }

        $message = SmsMessage::withoutGlobalScopes()->where('provider', $provider)->where('provider_message_id', $providerMessageId)->first();
        if (! $message || $message->status->rank() >= $status->rank()) {
            return $message;
        }

        $message->update([
            'status' => $status,
            'delivered_at' => $status === SmsStatus::Delivered ? now() : null,
            'error_code' => $status === SmsStatus::Failed ? $errorCode : $message->error_code,
        ]);

        return $message;
    }

    private function validate(string $body, string $key): void
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Message vide.');
        }
        if (mb_strlen($body) > config('sms.max_body_length')) {
            throw new InvalidArgumentException('Message trop long.');
        }
        if ($key === '' || strlen($key) > 120) {
            throw new InvalidArgumentException('Clé d\'idempotence invalide (1 à 120 caractères).');
        }
    }
}
