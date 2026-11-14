<?php

namespace App\Domain\Sms;

enum SmsStatus: string
{
    case Pending = 'pending';       // créé, pas encore traité
    case Blocked = 'blocked';       // refusé avant envoi (consentement/désinscription)
    case Sending = 'sending';       // pris en charge par un worker
    case Sent = 'sent';             // accepté par le fournisseur
    case Delivered = 'delivered';   // accusé de réception positif
    case Failed = 'failed';         // échec définitif
    case Unknown = 'unknown';       // erreur de transport : a peut-être été envoyé, à réconcilier, jamais renvoyé automatiquement

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Blocked => 'Bloqué',
            self::Sending => 'En cours',
            self::Sent => 'Envoyé',
            self::Delivered => 'Délivré',
            self::Failed => 'Échec',
            self::Unknown => 'Résultat inconnu',
        };
    }

    /** Ordre de progression : un accusé tardif ne doit jamais faire « reculer » un statut. */
    public function rank(): int
    {
        return match ($this) {
            self::Pending, self::Blocked => 0,
            self::Sending => 1,
            self::Sent, self::Unknown => 2,
            self::Delivered, self::Failed => 4,
        };
    }
}
