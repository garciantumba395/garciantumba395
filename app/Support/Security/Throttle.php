<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Limitation de débit nommée (valeurs : config/security.php > rate_limits). Chaque appel à attempt() compte une tentative.
 * Clé de comptage = nom du limiteur + identifiant fourni (utilisateur, IP, e-mail...).
 */
class Throttle
{
    /** Compte une tentative ; false si la limite est dépassée. */
    public static function attempt(string $name, string|int|null $key): bool
    {
        [$max, $decay] = self::limits($name);

        return RateLimiter::attempt(self::key($name, $key), $max, fn () => true, $decay);
    }

    /** La limite est-elle déjà atteinte (sans compter de tentative) ? */
    public static function tooMany(string $name, string|int|null $key): bool
    {
        return RateLimiter::tooManyAttempts(self::key($name, $key), self::limits($name)[0]);
    }

    /** Compte un échec (limiteurs « échecs seulement » comme le défi 2FA). */
    public static function hit(string $name, string|int|null $key): void
    {
        RateLimiter::hit(self::key($name, $key), self::limits($name)[1]);
    }

    public static function clear(string $name, string|int|null $key): void
    {
        RateLimiter::clear(self::key($name, $key));
    }

    /** Message utilisateur standard. */
    public static function message(string $name, string|int|null $key): string
    {
        return 'Trop de tentatives. Réessayez dans '.max(1, RateLimiter::availableIn(self::key($name, $key))).' secondes.';
    }

    /** @return array{0:int,1:int} */
    private static function limits(string $name): array
    {
        $c = config("security.rate_limits.$name");
        if (! is_array($c)) {
            throw new InvalidArgumentException("Limiteur inconnu : $name");
        }

        return [(int) $c['max'], (int) $c['decay']];
    }

    private static function key(string $name, string|int|null $key): string
    {
        return 'rl:'.$name.':'.sha1((string) $key);
    }
}
