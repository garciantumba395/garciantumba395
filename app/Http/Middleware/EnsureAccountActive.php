<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Un compte désactivé est déconnecté à la requête suivante (session et cookie « se souvenir de moi » invalidés). */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($user !== null && $user->disabled_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Ce compte est désactivé.']);
        }

        return $next($request);
    }
}
