<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * D10 : si security.require_email_verification est actif, un compte à l'adresse non vérifiée ne peut accéder à rien d'autre qu'à la page de
 * vérification et à la déconnexion. Enregistré comme middleware persistant Livewire : appliqué aussi aux actions d'une page déjà ouverte.
 */
class EnsureEmailIsVerifiedIfRequired
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && config('security.require_email_verification')
            && ! $request->routeIs('verification.*', 'logout')
            && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                abort(403, 'Adresse e-mail non vérifiée.');
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
