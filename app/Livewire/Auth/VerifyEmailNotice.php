<?php

namespace App\Livewire\Auth;

use App\Support\Security\Throttle;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Vérifiez votre adresse e-mail')]
class VerifyEmailNotice extends Component
{
    public bool $sent = false;

    public function mount()
    {
        if (Auth::user()->hasVerifiedEmail() || ! config('security.require_email_verification')) {
            return $this->redirectRoute(Auth::user()->homeRoute(), navigate: false);
        }
    }

    /** Renvoie le lien (3 par minute et par utilisateur). */
    public function resend(): void
    {
        $user = Auth::user();
        if ($user->hasVerifiedEmail()) {
            return;
        }
        if (! Throttle::attempt('email_verification', $user->id)) {
            $this->addError('resend', Throttle::message('email_verification', $user->id));

            return;
        }
        $user->sendEmailVerificationNotification();
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.verify-email-notice', ['email' => Auth::user()->email]);
    }
}
