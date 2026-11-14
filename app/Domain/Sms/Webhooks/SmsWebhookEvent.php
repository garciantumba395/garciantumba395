<?php

namespace App\Domain\Sms\Webhooks;

use App\Domain\Sms\SmsStatus;

/**
 * Événement de webhook NORMALISÉ, indépendant du fournisseur : chaque vérificateur traduit le format
 * propre à son fournisseur vers cet objet. Le reste du code (déduplication, accusés, désinscription) n'en connaît pas d'autre.
 */
final class SmsWebhookEvent
{
    private function __construct(
        public readonly string $eventId,
        public readonly SmsWebhookEventType $type,
        public readonly ?string $providerMessageId = null,
        public readonly ?SmsStatus $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $from = null,
        public readonly ?string $text = null,
    ) {}

    /** Accusé de réception : $status vaut Delivered ou Failed. */
    public static function deliveryReport(string $eventId, string $providerMessageId, SmsStatus $status, ?string $errorCode = null): self
    {
        return new self($eventId, SmsWebhookEventType::DeliveryReport, providerMessageId: $providerMessageId, status: $status, errorCode: $errorCode);
    }

    /** Message entrant : $from est un numéro E.164. Le texte n'est jamais stocké (il sert seulement à reconnaître un mot-clé). */
    public static function inbound(string $eventId, string $from, string $text): self
    {
        return new self($eventId, SmsWebhookEventType::Inbound, from: $from, text: $text);
    }
}
