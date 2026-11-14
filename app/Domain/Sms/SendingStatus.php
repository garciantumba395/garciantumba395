<?php

namespace App\Domain\Sms;

enum SendingStatus: string
{
    case Probation = 'probation';   // démarrage : limite journalière réduite, évaluation automatique
    case Validated = 'validated';   // validée automatiquement (ou par l'administrateur)
    case Suspended = 'suspended';   // envois réels bloqués (simulation toujours possible)

    public function label(): string
    {
        return match ($this) {
            self::Probation => 'Démarrage',
            self::Validated => 'Validée',
            self::Suspended => 'Suspendue',
        };
    }
}
