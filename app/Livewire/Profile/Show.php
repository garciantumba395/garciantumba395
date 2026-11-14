<?php

namespace App\Livewire\Profile;

use App\Models\User;
use App\Support\Security\Throttle;
use App\Support\Security\TwoFactor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Profil de l'utilisateur connecté : mot de passe, autres appareils, double authentification. Aucune entreprise requise. */
#[Layout('layouts.app')]
#[Title('Profil')]
class Show extends Component
{
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public string $logoutPassword = '';

    public string $setupCode = '';

    public string $twoFactorPassword = '';

    public string $twoFactorCode = '';

    /** Codes de secours en clair : affichés UNE SEULE FOIS juste après leur création. @var list<string> */
    public array $recoveryCodes = [];

    public function changePassword(): void
    {
        $user = $this->user();
        $this->guardRate($user, 'currentPassword');
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'confirmed', 'different:currentPassword', Password::min(10)],
        ]);
        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'Le mot de passe actuel est incorrect.');

            return;
        }

        $user->forceFill(['password' => $this->newPassword])->save();
        // Invalide les sessions des autres appareils (middleware AuthenticateSession) et le « se souvenir de moi ».
        Auth::logoutOtherDevices($this->newPassword);

        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        session()->flash('status', 'Mot de passe modifié. Les autres appareils ont été déconnectés.');
    }

    public function logoutOtherDevices(): void
    {
        $user = $this->user();
        $this->guardRate($user, 'logoutPassword');
        $this->validate(['logoutPassword' => ['required', 'string']]);
        if (! Hash::check($this->logoutPassword, $user->password)) {
            $this->addError('logoutPassword', 'Le mot de passe est incorrect.');

            return;
        }

        Auth::logoutOtherDevices($this->logoutPassword);
        $this->reset('logoutPassword');
        session()->flash('status', 'Les autres appareils ont été déconnectés.');
    }

    public function startTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        if (! $user->hasTwoFactorEnabled()) {
            $twoFactor->beginSetup($user);
        }
    }

    public function cancelTwoFactorSetup(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        if (! $user->hasTwoFactorEnabled()) {
            $twoFactor->disable($user);
        }
        $this->reset('setupCode');
    }

    public function confirmTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        $this->guardRate($user, 'setupCode');
        $this->validate(['setupCode' => ['required', 'string', 'max:16']]);

        $codes = $twoFactor->confirm($user, $this->setupCode);
        if ($codes === null) {
            $this->addError('setupCode', 'Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.');

            return;
        }

        $this->recoveryCodes = $codes;
        $this->reset('setupCode');
        session()->flash('status', 'Double authentification activée. Conservez vos codes de secours.');
    }

    public function disableTwoFactor(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        if ($twoFactor->isRequiredFor($user)) {
            $this->addError('twoFactorCode', 'La double authentification est obligatoire pour votre rôle.');

            return;
        }
        if (! $this->checkPasswordAndCode($user, $twoFactor)) {
            return;
        }

        $twoFactor->disable($user);
        $this->reset('twoFactorPassword', 'twoFactorCode', 'recoveryCodes');
        session()->flash('status', 'Double authentification désactivée.');
    }

    public function regenerateRecoveryCodes(TwoFactor $twoFactor): void
    {
        $user = $this->user();
        if (! $user->hasTwoFactorEnabled() || ! $this->checkPasswordAndCode($user, $twoFactor)) {
            return;
        }

        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($user);
        $this->reset('twoFactorPassword', 'twoFactorCode');
    }

    public function dismissRecoveryCodes(): void
    {
        $this->recoveryCodes = [];
    }

    /** Action sensible : mot de passe actuel ET code (TOTP ou secours). Limité en débit. */
    private function checkPasswordAndCode(User $user, TwoFactor $twoFactor): bool
    {
        $this->guardRate($user, 'twoFactorCode');
        $this->validate([
            'twoFactorPassword' => ['required', 'string'],
            'twoFactorCode' => ['required', 'string', 'max:32'],
        ]);

        if (! Hash::check($this->twoFactorPassword, $user->password)) {
            $this->addError('twoFactorPassword', 'Le mot de passe est incorrect.');

            return false;
        }
        if (! $twoFactor->verify($user, $this->twoFactorCode)) {
            $this->addError('twoFactorCode', 'Code invalide ou déjà utilisé (attendez le prochain code).');

            return false;
        }

        return true;
    }

    /** Compte la tentative ; au-delà de la limite, erreur de formulaire (aucune vérification n'est effectuée). */
    private function guardRate(User $user, string $field): void
    {
        if (! Throttle::attempt('sensitive_profile', $user->id)) {
            throw ValidationException::withMessages([$field => Throttle::message('sensitive_profile', $user->id)]);
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function render(TwoFactor $twoFactor)
    {
        $user = $this->user();
        $pendingSetup = $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled();

        return view('livewire.profile.show', [
            'user' => $user,
            'enabled' => $user->hasTwoFactorEnabled(),
            'pendingSetup' => $pendingSetup,
            'secret' => $pendingSetup ? $user->two_factor_secret : null,
            'uri' => $pendingSetup ? $twoFactor->otpauthUri($user) : null,
            'remaining' => $twoFactor->remainingRecoveryCodes($user),
            'required' => $twoFactor->isRequiredFor($user),
        ]);
    }
}
