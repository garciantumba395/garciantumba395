<?php

namespace App\Support\Security;

use InvalidArgumentException;

/**
 * TOTP (RFC 6238) / HOTP (RFC 4226), HMAC-SHA1 par défaut, secret en Base32 (RFC 4648).
 * Implémentation minimale vérifiée par les vecteurs de test de la RFC (tests/Unit/TotpTest.php).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Secret aléatoire de 160 bits (recommandation RFC 4226), encodé en Base32. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim(str_replace([' ', '-'], '', $base32), '='));
        $bits = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new InvalidArgumentException('Secret Base32 invalide.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    /** Compteur (pas de temps) correspondant à un horodatage Unix. */
    public static function step(int $timestamp, int $period = 30): int
    {
        return intdiv($timestamp, $period);
    }

    /** Code HOTP (RFC 4226) pour un compteur donné. */
    public static function hotp(string $base32Secret, int $counter, int $digits = 6, string $algo = 'sha1'): string
    {
        $key = self::base32Decode($base32Secret);
        $hash = hash_hmac($algo, pack('J', $counter), $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** Code TOTP (RFC 6238) à un horodatage donné. */
    public static function at(string $base32Secret, int $timestamp, int $digits = 6, int $period = 30, string $algo = 'sha1'): string
    {
        return self::hotp($base32Secret, self::step($timestamp, $period), $digits, $algo);
    }

    /**
     * Cherche le pas dont le code correspond, dans la fenêtre ±$window autour de $timestamp.
     * Retourne le pas trouvé (à mémoriser pour interdire le rejeu) ou null. Comparaison à temps constant.
     */
    public static function findStep(string $base32Secret, string $code, int $timestamp, int $window = 1, int $digits = 6, int $period = 30): ?int
    {
        if (! preg_match('/^\d{'.$digits.'}$/', $code)) {
            return null;
        }
        $current = self::step($timestamp, $period);
        $found = null;
        // Tous les pas de la fenêtre sont évalués (pas de sortie anticipée).
        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if ($step >= 0 && hash_equals(self::hotp($base32Secret, $step, $digits), $code) && ($found === null || $step > $found)) {
                $found = $step;
            }
        }

        return $found;
    }

    /** URI otpauth:// (format Key Uri) à saisir/scanner dans une application d'authentification. */
    public static function uri(string $issuer, string $account, string $base32Secret, int $digits = 6, int $period = 30): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $base32Secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => $digits,
            'period' => $period,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
