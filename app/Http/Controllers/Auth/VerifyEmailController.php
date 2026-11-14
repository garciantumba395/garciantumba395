<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

/** Lien signé reçu par e-mail. EmailVerificationRequest vérifie que l'identifiant et l'empreinte correspondent à l'utilisateur connecté. */
class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill(); // marque l'adresse vérifiée et émet l'événement Verified
        }

        return redirect()->route($request->user()->homeRoute())->with('status', 'Adresse e-mail vérifiée. Merci !');
    }
}
