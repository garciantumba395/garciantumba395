<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarif global de la plateforme (pas lié à une entreprise : non filtré par CompanyScope). */
class SmsRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
