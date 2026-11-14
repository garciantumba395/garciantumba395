<?php

namespace App\Support\Security;

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 2FA TOTP : activation (secret chiffré, confirmation par un premier code), vérification avec anti-rejeu,
 * codes de secours à usage unique (stockés sous forme de HMAC-SHA256, jamais en clair), désactivation.
 */
class TwoFactor
{
    /** Génère (ou régénère) un secret NON confirmé : la 2FA n'est active qu'après confirm(). */
    public function beginSetup(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    public function otpauthUri(User $user): string
    {
        return Totp::uri(
            config('security.totp.issuer') ?: config('app.name'),
            $user->email,
            $user->two_factor_secret,
            (int) config('security.totp.digits'),
            (int) config('security.totp.period'),
        );
    }

    /**
     * Confirme la configuration avec un premier code valide. Retourne les codes de secours EN CLAIR (affichés une seule fois),
     * ou null si le code est invalide ou si aucune configuration n'est en cours.
     *
     * @return list<string>|null
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            return null;
        }
        if (! $this->acceptTotp($user, $code)) {
            return null;
        }
        $plain = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map($this->hashCode(...), $plain),
        ])->save();

        return $plain;
    }

    /** Vérifie un code TOTP d'un utilisateur dont la 2FA est active (rejeu refusé). */
    public function verifyTotp(User $user, string $code): bool
    {
        return $user->hasTwoFactorEnabled() && $this->acceptTotp($user, $code);
    }

    /** Consomme un code de secours (usage unique). */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }
        $hash = $this->hashCode($code);

        return DB::transaction(function () use ($user, $hash) {
            $fresh = User::query()->lockForUpdate()->find($user->id);
            $codes = $fresh?->two_factor_recovery_codes ?? [];
            $index = null;
            foreach ($codes as $i => $stored) {
                if (hash_equals($stored, $hash)) {
                    $index = $i;
                }
            }
            if ($index === null) {
                return false;
            }
            unset($codes[$index]);
            $fresh->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->syncOriginal();

            return true;
        });
    }

    /** Défi de connexion / action sensible : code TOTP (6 chiffres) ou code de secours. */
    public function verify(User $user, string $input): bool
    {
        $input = trim($input);
        if (preg_match('/^\d{'.(int) config('security.totp.digits').'}$/', $input)) {
            return $this->verifyTotp($user, $input);
        }

        return $input !== '' && $this->verifyRecoveryCode($user, $input);
    }

    /**
     * Nouveaux codes de secours (les anciens sont invalidés).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $plain = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map($this->hashCode(...), $plain)])->save();

        return $plain;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /** La 2FA est-elle obligatoire pour cet utilisateur (require_2fa_for_admins : owners/admins d'entreprise et administrateurs de plateforme) ? */
    public function isRequiredFor(User $user): bool
    {
        if (! config('security.require_2fa_for_admins')) {
            return false;
        }

        return (bool) $user->is_platform_admin
            || $user->companies()->wherePivotIn('role', [CompanyRole::Owner->value, CompanyRole::Admin->value])->exists();
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Accepte le code si son pas est strictement supérieur au dernier pas accepté. La mise à jour est conditionnelle
     * (UPDATE ... WHERE last_step < pas) : deux requêtes simultanées avec le même code ne peuvent pas toutes deux réussir.
     */
    private function acceptTotp(User $user, string $code): bool
    {
        $step = Totp::findStep(
            (string) $user->two_factor_secret,
            trim($code),
            now()->timestamp, // Carbon (figeable dans les tests) : temps Unix réel, indépendant du fuseau de l'application
            (int) config('security.totp.window'),
            (int) config('security.totp.digits'),
            (int) config('security.totp.period'),
        );
        if ($step === null) {
            return false;
        }

        $updated = DB::table('users')->where('id', $user->id)
            ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
            ->update(['two_factor_last_step' => $step]);
        if ($updated === 1) {
            $user->forceFill(['two_factor_last_step' => $step])->syncOriginal();
        }

        return $updated === 1;
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < (int) config('security.totp.recovery_codes'); $i++) {
            $raw = strtolower(substr(Totp::base32Encode(random_bytes(7)), 0, 10)); // 50 bits d'entropie
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return $codes;
    }

    /** HMAC-SHA256 avec la clé de l'application (codes à haute entropie : un haché lent n'apporte rien et pénaliserait la vérification). */
    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', strtolower(str_replace([' ', '-'], '', trim($code))), (string) config('app.key'));
    }
}
