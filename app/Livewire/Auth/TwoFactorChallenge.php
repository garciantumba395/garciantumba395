<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Security\Throttle;
use App\Support\Security\TwoFactor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Seconde étape de la connexion pour les comptes avec 2FA. L'utilisateur n'est PAS connecté tant que le code n'est pas valide :
 * seule l'identité « en attente » (id, expiration) est en session, jamais dans l'état client.
 */
#[Layout('layouts.guest')]
#[Title('Vérification en deux étapes')]
class TwoFactorChallenge extends Component
{
    public const SESSION_KEY = 'two_factor.pending';

    public string $code = '';

    public function mount()
    {
        if ($this->pendingUser() === null) {
            return $this->redirectRoute('login', navigate: false);
        }
    }

    public function verify(TwoFactor $twoFactor)
    {
        $this->validate(['code' => ['required', 'string', 'max:32']]);

        $user = $this->pendingUser();
        if ($user === null) {
            return $this->redirectRoute('login', navigate: false);
        }

        if (Throttle::tooMany('two_factor_challenge', $user->id)) {
            throw ValidationException::withMessages(['code' => Throttle::message('two_factor_challenge', $user->id)]);
        }

        if (! $twoFactor->verify($user, $this->code)) {
            Throttle::hit('two_factor_challenge', $user->id);
            $this->reset('code');
            throw ValidationException::withMessages(['code' => 'Code invalide ou déjà utilisé.']);
        }

        $pending = session()->pull(self::SESSION_KEY);
        Throttle::clear('two_factor_challenge', $user->id);
        Auth::login($user, (bool) ($pending['remember'] ?? false));
        session()->regenerate();

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    /** L'utilisateur en attente, ou null (aucun défi, expiré, compte sans 2FA). */
    private function pendingUser(): ?User
    {
        $pending = session(self::SESSION_KEY);
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            session()->forget(self::SESSION_KEY);

            return null;
        }
        $user = User::find($pending['id'] ?? 0);

        // Un compte désactivé entre le mot de passe et le code (étape 8) ne doit jamais obtenir de session.
        return $user && $user->disabled_at === null && $user->hasTwoFactorEnabled() ? $user : null;
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
