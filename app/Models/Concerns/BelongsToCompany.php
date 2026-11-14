<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * À utiliser sur TOUT modèle métier appartenant à une entreprise.
 * - filtre automatiquement les requêtes sur l'entreprise active (fail-closed) ;
 * - renseigne company_id à la création, ou refuse si aucune entreprise active.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if ($model->company_id === null) {
                $id = app(CurrentCompany::class)->id();
                if ($id === null) {
                    throw new LogicException('Aucune entreprise active : création refusée pour '.$model::class);
                }
                $model->company_id = $id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
