<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lien public de désabonnement. Volontairement SANS BelongsToCompany : il est consulté par un destinataire non connecté, sans contexte
 * d'entreprise. La clé d'accès est le code aléatoire ; l'entreprise est portée par la ligne elle-même.
 */
class UnsubscribeLink extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
