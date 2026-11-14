<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'country', 'is_active'];

    // Même défaut que la migration, pour les instances non rechargées.
    protected $attributes = ['country' => 'CD', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
}
