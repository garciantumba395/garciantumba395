<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['obtained_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Consentement valide : non révoqué et, si configuré, non expiré. */
    public function scopeActive(Builder $q): Builder
    {
        $q->whereNull($q->qualifyColumn('revoked_at'));
        if ($days = config('consent.max_age_days')) {
            $q->where($q->qualifyColumn('obtained_at'), '>=', now()->subDays($days));
        }

        return $q;
    }

    public function sourceLabel(): string
    {
        return config('consent.sources.'.$this->source, $this->source);
    }
}
