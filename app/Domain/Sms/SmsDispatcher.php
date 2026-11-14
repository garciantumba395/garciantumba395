<?php

namespace App\Domain\Sms;

use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
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
 *  4. une erreur de transport laisse le statut « unknown » et n'est JAMAIS renvoyée automatiquement.
 * (Étape 5 : le débit de crédits s'insérera ici, avant l'appel au fournisseur, avec la même clé d'idempotence.)
 */
class SmsDispatcher
{
    public function __construct(
        private SmsManager $manager,
        private SendEligibility $eligibility,
        private CurrentCompany $current,
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

        // Prise en charge atomique : un seul appelant peut passer de pending à sending.
        $claimed = SmsMessage::whereKey($message->id)->where('status', SmsStatus::Pending->value)
            ->update(['status' => SmsStatus::Sending->value, 'attempts' => DB::raw('attempts + 1'), 'sending_at' => now()]);
        if ($claimed === 0) {
            return $message->fresh();
        }

        try {
            $result = $provider->send(new SmsRequest($contact->phone, $body, 'm'.$message->id, $senderId));
        } catch (Throwable $e) {
            // 4. Résultat inconnu : le message a pu partir. Pas de renvoi automatique.
            $message->update(['status' => SmsStatus::Unknown, 'error_code' => 'TRANSPORT_ERROR', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);

            return $message;
        }

        if ($result->accepted) {
            $message->update(['status' => SmsStatus::Sent, 'provider_message_id' => $result->providerMessageId, 'sent_at' => now()]);
        } else {
            $message->update(['status' => SmsStatus::Failed, 'error_code' => $result->errorCode, 'error_message' => mb_substr((string) $result->errorMessage, 0, 500)]);
        }

        return $message;
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
