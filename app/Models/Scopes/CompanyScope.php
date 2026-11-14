<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Isolation par entreprise. Sans entreprise active : aucun résultat (fail-closed). */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentCompany::class)->id();

        if ($id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $id);
    }
}
