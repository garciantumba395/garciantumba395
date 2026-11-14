<?php

namespace App\Livewire\Concerns;

use App\Support\Security\Throttle;
use Illuminate\Support\Facades\Auth;

/** Limitation de débit des actions d'administration de la plateforme (limiteur « admin_actions », par utilisateur). */
trait ThrottlesAdminActions
{
    protected function throttleAdminAction(): void
    {
        abort_unless(Throttle::attempt('admin_actions', Auth::id()), 429, 'Trop d\'actions d\'administration. Réessayez dans un instant.');
    }
}
