<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\Auth;

/** Contrôle « administrateur de plateforme », à appeler au montage, dans CHAQUE action et au rendu (l'état Livewire vient du client). */
trait AuthorizesPlatformAdmin
{
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->is_platform_admin && Auth::user()->disabled_at === null, 403);
    }
}
