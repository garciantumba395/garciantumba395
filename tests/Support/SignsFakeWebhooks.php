<?php

namespace Tests\Support;

use App\Domain\Sms\Webhooks\HmacSignature;
use App\Models\Company;
use App\Models\SmsMessage;
use Illuminate\Testing\TestResponse;

/** Aides de test pour le webhook du fournisseur FICTIF (secret de test uniquement, aucun réseau). */
trait SignsFakeWebhooks
{
    protected string $webhookSecret = 'test-secret-not-real';

    /** En-têtes signés selon le format du fournisseur fictif (voir FakeSmsWebhookVerifier). @return array<string,string> */
    protected function fakeHeaders(string $body, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();

        return [
            'X-Fake-Timestamp' => (string) $timestamp,
            'X-Fake-Signature' => HmacSignature::sign($secret ?? $this->webhookSecret, $timestamp.'.'.$body),
        ];
    }

    /** @param array<string,string>|null $headers null = en-têtes correctement signés */
    protected function postWebhook(array|string $payload, ?array $headers = null, string $provider = 'fake'): TestResponse
    {
        $body = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
        $headers ??= $this->fakeHeaders($body);

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/webhooks/sms/'.$provider, [], [], [], $server, $body);
    }

    protected function configureWebhookSecret(): void
    {
        config(['sms.webhooks.fake_secret' => $this->webhookSecret]);
    }

    protected function makeMessage(Company $company, string $phone, string $status = 'sent', bool $simulated = false, ?string $providerMessageId = null, ?\DateTimeInterface $createdAt = null, ?\DateTimeInterface $updatedAt = null, string $provider = 'fake'): SmsMessage
    {
        static $n = 0;
        $n++;

        return SmsMessage::withoutGlobalScopes()->forceCreate([
            'company_id' => $company->id,
            'idempotency_key' => 'test-'.$n,
            'phone' => $phone,
            'body' => 'Corps de test',
            'status' => $status,
            'provider' => $provider,
            'simulated' => $simulated,
            'provider_message_id' => $providerMessageId,
            'segments' => 1,
            'encoding' => 'gsm7',
            'cost_units' => 5000,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $updatedAt ?? $createdAt ?? now(),
        ]);
    }
}
