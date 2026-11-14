<?php

namespace App\Livewire\Auth;

use App\Support\Security\Throttle;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Mot de passe oublié')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $key = request()->ip().'|'.strtolower($this->email);
        if (! Throttle::attempt('password_forgot', $key)) {
            $this->addError('email', Throttle::message('password_forgot', $key));

            return;
        }

        Password::sendResetLink(['email' => $this->email]);

        // Réponse identique que le compte existe ou non (pas d'énumération de comptes).
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
