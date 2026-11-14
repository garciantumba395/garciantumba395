<?php

namespace App\Domain\Sms\Webhooks;

/** Briques réutilisables par les vérificateurs HMAC (n'impose aucun format de fournisseur). */
final class HmacSignature
{
    public static function sign(string $secret, string $signedPayload, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $signedPayload, $secret);
    }

    /** Comparaison en temps constant ; faux si le secret est vide ou la signature absente. */
    public static function matches(string $secret, string $signedPayload, ?string $signature, string $algo = 'sha256'): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($secret, $signedPayload, $algo), strtolower(trim($signature)));
    }

    /** Horodatage Unix accepté : entier strict, écart absolu (passé OU futur) <= tolérance. */
    public static function timestampWithinTolerance(?string $timestamp, int $toleranceSeconds, ?int $now = null): bool
    {
        if ($timestamp === null || ! preg_match('/^\d{1,12}$/', $timestamp)) {
            return false;
        }

        return abs(($now ?? time()) - (int) $timestamp) <= $toleranceSeconds;
    }
}
