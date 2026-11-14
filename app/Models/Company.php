<?php

namespace App\Models;

use App\Domain\Sms\SendingStatus;
use App\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'country', 'is_active', 'legal_name', 'legal_address', 'legal_email', 'legal_phone', 'legal_rccm', 'legal_tax_id', 'legal_act'];
    // sending_status, daily_send_limit : jamais assignables en masse (SendingPolicy / administration uniquement).

    // Même défaut que la migration, pour les instances non rechargées.
    protected $attributes = ['country' => 'CD', 'is_active' => true, 'sending_status' => 'probation'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sending_status' => SendingStatus::class, 'sending_status_changed_at' => 'datetime'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** Génère un slug unique à partir du nom. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'entreprise';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Crée une entreprise et en fait le propriétaire de $owner (atomique). */
    public static function createFor(User $owner, string $name): self
    {
        return DB::transaction(function () use ($owner, $name) {
            $company = static::create(['name' => $name, 'slug' => static::uniqueSlug($name)]);
            $company->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);

            return $company;
        });
    }

    /** Nom sous lequel l'expéditeur est identifié dans les messages (art. 66 du Code du numérique). */
    public function senderName(): string
    {
        return trim((string) $this->legal_name) !== '' ? trim($this->legal_name) : $this->name;
    }

    /** Identité minimale exigée pour un envoi RÉEL de publicité : nom, adresse géographique et adresse e-mail (art. 66). */
    public function hasCompleteLegalIdentity(): bool
    {
        return trim((string) $this->legal_name) !== '' && trim((string) $this->legal_address) !== '' && trim((string) $this->legal_email) !== '';
    }
}
