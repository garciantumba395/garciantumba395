<?php

namespace App\Http\Middleware;

use App\Support\Security\TwoFactor;
use Closure;
use Illuminate\Http\Request;

/**
 * Si security.require_2fa_for_admins est actif : un owner/admin d'entreprise ou un administrateur de plateforme sans 2FA
 * ne peut rien faire d'autre que configurer sa 2FA (/profil) ou se déconnecter. Sans effet par défaut (D10 non tranchée).
 * Enregistré comme middleware persistant Livewire : appliqué aussi aux actions Livewire d'une page déjà ouverte.
 */
class EnsureTwoFactorForAdmins
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && config('security.require_2fa_for_admins')
            && ! $request->routeIs('profile', 'logout')
            && ! $user->hasTwoFactorEnabled()
            && $this->twoFactor->isRequiredFor($user)) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                abort(403, 'Double authentification obligatoire.');
            }

            return redirect()->route('profile')->with('status', 'La double authentification est obligatoire pour votre rôle : activez-la pour continuer.');
        }

        return $next($request);
    }
}
