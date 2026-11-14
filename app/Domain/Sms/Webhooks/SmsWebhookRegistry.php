<?php

namespace App\Domain\Sms\Webhooks;

use Closure;

/**
 * Registre des vérificateurs de webhook par fournisseur. Un fournisseur sans vérificateur enregistré n'a PAS de point d'entrée
 * (fail-closed). Indépendant de SmsManager : l'API d'envoi existante n'est pas modifiée.
 */
class SmsWebhookRegistry
{
    /** @var array<string,Closure():SmsWebhookVerifier> */
    private array $factories = [];

    public function __construct()
    {
        $this->factories[FakeSmsWebhookVerifier::PROVIDER] = fn () => new FakeSmsWebhookVerifier;
    }

    /** Enregistre le vérificateur d'un fournisseur (même nom que SmsProvider::name()). */
    public function extend(string $provider, Closure $factory): void
    {
        $this->factories[$provider] = $factory;
    }

    public function verifier(string $provider): ?SmsWebhookVerifier
    {
        return isset($this->factories[$provider]) ? ($this->factories[$provider])() : null;
    }
}
