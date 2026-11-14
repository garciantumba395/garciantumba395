<?php

namespace Tests\Feature\Sms;

use App\Domain\Sms\Webhooks\FakeSmsWebhookVerifier;
use App\Domain\Sms\Webhooks\HmacSignature;
use App\Domain\Sms\Webhooks\SmsWebhookVerifier;
use Tests\Contracts\SmsWebhookVerifierContractTest;

class FakeSmsWebhookVerifierContractTest extends SmsWebhookVerifierContractTest
{
    protected function makeVerifier(): SmsWebhookVerifier
    {
        return new FakeSmsWebhookVerifier;
    }

    protected function setSecret(?string $secret): void
    {
        config(['sms.webhooks.fake_secret' => $secret ?? '']);
    }

    protected function validBody(): string
    {
        return json_encode(['event_id' => 'contract-1', 'type' => 'delivery_report', 'message_id' => 'fake-1', 'status' => 'delivered']);
    }

    protected function signedHeaders(string $body, int $timestamp, string $secret): array
    {
        return ['x-fake-timestamp' => (string) $timestamp, 'x-fake-signature' => HmacSignature::sign($secret, $timestamp.'.'.$body)];
    }
}
