<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['name', 'phone', 'email'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_contact')->withTimestamps();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** Contacts ciblables : consentement valide ET numéro non désinscrit. Source unique de vérité pour les envois. */
    public function scopeSendable(Builder $q): Builder
    {
        return $q->whereExists(fn ($c) => $c->from('consents')
            ->whereColumn('consents.contact_id', 'contacts.id')
            ->whereColumn('consents.company_id', 'contacts.company_id')
            ->whereNull('consents.revoked_at')
            ->when(config('consent.max_age_days'), fn ($x, $d) => $x->where('consents.obtained_at', '>=', now()->subDays($d))))
            ->whereNotExists(fn ($c) => $c->from('suppressions')
                ->whereColumn('suppressions.phone', 'contacts.phone')
                ->whereColumn('suppressions.company_id', 'contacts.company_id')
                ->whereNull('suppressions.lifted_at'));
    }

    /** Ajoute has_consent et is_suppressed (booléens) pour l'affichage. */
    public function scopeWithConsentStatus(Builder $q): Builder
    {
        return $q->addSelect(['contacts.*'])->selectRaw(
            'exists (select 1 from consents where consents.contact_id = contacts.id and consents.company_id = contacts.company_id and consents.revoked_at is null) as has_consent'
        )->selectRaw(
            'exists (select 1 from suppressions where suppressions.phone = contacts.phone and suppressions.company_id = contacts.company_id and suppressions.lifted_at is null) as is_suppressed'
        );
    }
}
