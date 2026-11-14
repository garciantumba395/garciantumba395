<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Entrée du journal d'audit de la plateforme : AJOUT SEUL (comme le grand livre).
 * Volontairement SANS BelongsToCompany : c'est un journal de plateforme (company_id facultatif), lu uniquement
 * par les administrateurs de la plateforme ; aucune page d'entreprise ne l'expose. Écriture via AuditLogger uniquement.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit est en ajout seul : entrée non modifiable.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit est en ajout seul : entrée non supprimable.'));
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
