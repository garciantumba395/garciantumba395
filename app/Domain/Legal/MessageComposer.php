<?php

namespace App\Domain\Legal;

use App\Models\Company;
use App\Models\Contact;

/**
 * Compose le texte FINAL d'un SMS publicitaire : le corps saisi + le pied légal (art. 66, 72, 348 du Code du numérique) :
 *   « Publicité - {expéditeur}. Désabonnement : {lien} »
 * Le pied consomme des caractères : il est inclus dans le calcul des segments et des coûts (estimation comme envoi).
 */
class MessageComposer
{
    public function __construct(private UnsubscribeLinks $links) {}

    /** Message réel pour un destinataire (crée le lien de désabonnement du destinataire si besoin). */
    public function compose(string $body, Contact $contact, Company $company): string
    {
        if (! config('legal.enforce')) {
            return $body;
        }

        return $this->footer($body, $company, $this->links->url($this->links->forContact($contact)->code));
    }

    /** Même texte avec un lien factice de même longueur : estimation sans effet de bord. */
    public function composeForEstimate(string $body, Company $company): string
    {
        return config('legal.enforce') ? $this->footer($body, $company, $this->links->placeholderUrl()) : $body;
    }

    public function footerLength(Company $company): int
    {
        return mb_strlen($this->composeForEstimate('', $company));
    }

    private function footer(string $body, Company $company, string $url): string
    {
        return rtrim($body)."\n".config('legal.footer.advertising_mention').' - '.$company->senderName().'. '.config('legal.footer.unsubscribe_label').': '.$url;
    }
}
