<?php

namespace App\Domain\Campaigns;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Preparing = 'preparing';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Scheduled => 'Programmée',
            self::Preparing => 'Préparation',
            self::Sending => 'Envoi en cours',
            self::Paused => 'En pause',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }

    /** Un job d'envoi n'agit que dans ces états (pause/annulation arrêtent immédiatement les envois). */
    public function isActive(): bool
    {
        return in_array($this, [self::Preparing, self::Sending], true);
    }
}
