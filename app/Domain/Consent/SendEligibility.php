<?php

namespace App\Domain\Consent;

use App\Models\Contact;
use App\Models\Suppression;

/**
 * Vérification à appeler AVANT CHAQUE ENVOI (au moment de l'envoi, pas à la création de la campagne).
 * Lit toujours la base : aucun cache, pour qu'une désinscription prenne effet immédiatement.
 */
class SendEligibility
{
    public function for(Contact $contact): Eligibility
    {
        // Passe par le scope Sendable : une seule définition de « ciblable » pour envois unitaires et en masse.
        $fresh = Contact::withoutGlobalScopes()->whereKey($contact->id)
            ->where('company_id', $contact->company_id)->first();
        if (! $fresh) {
            return Eligibility::NoConsent;
        }

        if (Suppression::withoutGlobalScopes()->active()
            ->where('company_id', $contact->company_id)->where('phone', $fresh->phone)->exists()) {
            return Eligibility::Suppressed;
        }

        $sendable = Contact::withoutGlobalScopes()->sendable()->whereKey($contact->id)
            ->where('contacts.company_id', $contact->company_id)->exists();

        return $sendable ? Eligibility::Eligible : Eligibility::NoConsent;
    }
}
