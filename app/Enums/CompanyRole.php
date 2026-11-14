<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Member => 'Membre',
        };
    }

    /** Peut modifier les paramètres de l'entreprise et gérer les membres. */
    public function canManageCompany(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
