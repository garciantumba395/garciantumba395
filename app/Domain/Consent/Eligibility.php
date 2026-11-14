<?php

namespace App\Domain\Consent;

enum Eligibility: string
{
    case Eligible = 'eligible';
    case NoConsent = 'no_consent';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return match ($this) {
            self::Eligible => 'Consentement valide',
            self::NoConsent => 'Sans consentement',
            self::Suppressed => 'Désinscrit',
        };
    }
}
