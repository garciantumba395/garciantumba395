<?php

namespace App\Domain\Sms\Webhooks;

/**
 * Contrat d'un vérificateur de webhook, un par fournisseur (enregistré dans SmsWebhookRegistry).
 * Il porte TOUT ce qui est propre au fournisseur : en-têtes de signature, algorithme, format du corps.
 * Le contrôleur, lui, est générique et fail-closed.
 */
interface SmsWebhookVerifier
{
    /** false si aucun secret n'est configuré : le contrôleur refuse alors TOUTE requête (fail-closed). */
    public function isConfigured(): bool;

    /**
     * Authentifie la requête à partir du corps BRUT (octets reçus, jamais re-sérialisés) et des en-têtes
     * (clés en minuscules). La comparaison de signature DOIT être en temps constant (hash_equals) et un horodatage
     * hors de la fenêtre config('sms.webhooks.tolerance_seconds') DOIT être refusé (passé comme futur).
     *
     * @param  array<string,string>  $headers
     *
     * @throws InvalidWebhookSignatureException si la requête n'est pas authentique (et uniquement dans ce cas)
     */
    public function verify(string $rawBody, array $headers): void;

    /**
     * Traduit un corps DÉJÀ authentifié en événement normalisé.
     *
     * @throws InvalidWebhookPayloadException si le corps est illisible ou incomplet
     */
    public function parse(string $rawBody): SmsWebhookEvent;
}
