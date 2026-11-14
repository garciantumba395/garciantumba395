<?php

namespace App\Models;

use App\Domain\Credits\LedgerType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Écriture du grand livre : AJOUT SEUL. Toute modification/suppression est refusée. */
class CreditLedgerEntry extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le grand livre est en ajout seul : écriture non modifiable.'));
        static::deleting(fn () => throw new LogicException('Le grand livre est en ajout seul : écriture non supprimable.'));
    }

    protected function casts(): array
    {
        return ['type' => LedgerType::class, 'simulated' => 'boolean', 'created_at' => 'datetime'];
    }
}
