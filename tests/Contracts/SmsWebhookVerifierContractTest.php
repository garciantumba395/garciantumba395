<?php

namespace Tests\Contracts;

use App\Domain\Sms\Webhooks\InvalidWebhookPayloadException;
use App\Domain\Sms\Webhooks\InvalidWebhookSignatureException;
use App\Domain\Sms\Webhooks\SmsWebhookVerifier;
use Tests\TestCase;

/**
 * CONTRAT DE TEST d'un SmsWebhookVerifier (voir docs/PROVIDERS.md). Une classe concrète fournit : le vérificateur, la pose/le retrait
 * du secret de test (jamais un vrai secret), un corps valide et la signature des requêtes dans le format du fournisseur.
 */
abstract class SmsWebhookVerifierContractTest extends TestCase
{
    protected const SECRET = 'contract-secret-not-real';

    abstract protected function makeVerifier(): SmsWebhookVerifier;

    /** Configure (string) ou retire (null) le secret du fournisseur. */
    abstract protected function setSecret(?string $secret): void;

    /** Un corps que parse() accepte. */
    abstract protected function validBody(): string;

    /**
     * En-têtes (clés en minuscules) que le fournisseur enverrait pour ce corps, signés avec $secret à l'instant $timestamp.
     *
     * @return array<string,string>
     */
    abstract protected function signedHeaders(string $body, int $timestamp, string $secret): array;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.webhooks.tolerance_seconds' => 300]);
        $this->setSecret(self::SECRET);
    }

    public function test_without_secret_the_verifier_is_not_configured_and_refuses_everything(): void
    {
        $this->setSecret(null);
        $body = $this->validBody();

        $this->assertFalse($this->makeVerifier()->isConfigured());
        foreach ([[], $this->signedHeaders($body, time(), ''), $this->signedHeaders($body, time(), self::SECRET)] as $headers) {
            $this->expectRefusal($body, $headers);
        }
    }

    public function test_valid_signature_passes_and_the_body_parses_to_an_event_with_an_id(): void
    {
        $body = $this->validBody();
        $verifier = $this->makeVerifier();

        $this->assertTrue($verifier->isConfigured());
        $verifier->verify($body, $this->signedHeaders($body, time(), self::SECRET));
        $event = $verifier->parse($body);
        $this->assertNotSame('', $event->eventId);
        $this->assertLessThanOrEqual(120, strlen($event->eventId)); // colonne event_id(120)
    }

    public function test_missing_wrong_secret_and_tampered_signatures_are_refused(): void
    {
        $body = $this->validBody();

        $this->expectRefusal($body, []);
        $this->expectRefusal($body, $this->signedHeaders($body, time(), 'autre-secret'));
        $this->expectRefusal($body.' ', $this->signedHeaders($body, time(), self::SECRET));
        $this->expectRefusal($body, array_map(fn ($v) => $v.'0', $this->signedHeaders($body, time(), self::SECRET)));
    }

    public function test_stale_and_future_timestamps_are_refused(): void
    {
        $body = $this->validBody();

        $this->expectRefusal($body, $this->signedHeaders($body, time() - 3600, self::SECRET));
        $this->expectRefusal($body, $this->signedHeaders($body, time() + 3600, self::SECRET));
    }

    public function test_unparseable_body_raises_only_the_payload_exception(): void
    {
        foreach (['', 'pas du json', '[]', '{}', '{"x":1}'] as $garbage) {
            try {
                $this->makeVerifier()->parse($garbage);
                $this->fail('parse() aurait dû refuser : '.$garbage);
            } catch (InvalidWebhookPayloadException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function expectRefusal(string $body, array $headers): void
    {
        try {
            $this->makeVerifier()->verify($body, $headers);
            $this->fail('verify() aurait dû refuser la requête.');
        } catch (InvalidWebhookSignatureException) {
            $this->addToAssertionCount(1); // seul ce type d'exception est permis
        }
    }
}
