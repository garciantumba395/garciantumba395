<?php

namespace App\Domain\Sms\Webhooks;

use App\Domain\Sms\SmsStatus;

/**
 * Vérificateur du fournisseur FICTIF. Le format ci-dessous est celui de NOTRE simulation, pas celui d'un vrai fournisseur :
 *  - en-têtes : X-Fake-Timestamp (secondes Unix) et X-Fake-Signature = hex(HMAC-SHA256(secret, "{timestamp}.{corps brut}")) ;
 *  - secret : config('sms.webhooks.fake_secret') (vide par défaut = tout est refusé) ;
 *  - corps JSON : {"event_id":"…","type":"delivery_report","message_id":"…","status":"delivered|failed","error_code":"…"}
 *    ou {"event_id":"…","type":"inbound","from":"+24381…","text":"STOP"}.
 */
class FakeSmsWebhookVerifier implements SmsWebhookVerifier
{
    public const PROVIDER = 'fake';

    public function isConfigured(): bool
    {
        return $this->secret() !== '';
    }

    public function verify(string $rawBody, array $headers): void
    {
        $timestamp = $headers['x-fake-timestamp'] ?? null;

        if (! $this->isConfigured()
            || ! HmacSignature::timestampWithinTolerance($timestamp, (int) config('sms.webhooks.tolerance_seconds'))
            || ! HmacSignature::matches($this->secret(), $timestamp.'.'.$rawBody, $headers['x-fake-signature'] ?? null)) {
            throw new InvalidWebhookSignatureException('Signature de webhook invalide.');
        }
    }

    public function parse(string $rawBody): SmsWebhookEvent
    {
        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            throw new InvalidWebhookPayloadException('Corps JSON invalide.');
        }

        $eventId = $data['event_id'] ?? null;
        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 120) {
            throw new InvalidWebhookPayloadException('event_id invalide.');
        }

        switch ($data['type'] ?? null) {
            case SmsWebhookEventType::DeliveryReport->value:
                $messageId = $data['message_id'] ?? null;
                $status = SmsStatus::tryFrom((string) ($data['status'] ?? ''));
                $error = $data['error_code'] ?? null;
                if (! is_string($messageId) || $messageId === '' || strlen($messageId) > 100
                    || ! in_array($status, [SmsStatus::Delivered, SmsStatus::Failed], true)
                    || ($error !== null && (! is_string($error) || strlen($error) > 50))) {
                    throw new InvalidWebhookPayloadException('Accusé de réception invalide.');
                }

                return SmsWebhookEvent::deliveryReport($eventId, $messageId, $status, $error);

            case SmsWebhookEventType::Inbound->value:
                $from = $data['from'] ?? null;
                $text = $data['text'] ?? null;
                if (! is_string($from) || ! preg_match('/^\+[1-9]\d{6,14}$/', $from) || ! is_string($text) || strlen($text) > 1600) {
                    throw new InvalidWebhookPayloadException('Message entrant invalide.');
                }

                return SmsWebhookEvent::inbound($eventId, $from, $text);
        }

        throw new InvalidWebhookPayloadException('Type d\'événement inconnu.');
    }

    private function secret(): string
    {
        return (string) config('sms.webhooks.fake_secret', '');
    }
}
