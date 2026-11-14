<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Réservé aux administrateurs de la plateforme (drapeau users.is_platform_admin, attribué en console uniquement). */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return $next($request);
    }
}
