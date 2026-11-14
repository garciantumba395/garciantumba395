<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// is_platform_admin volontairement absent de $fillable : jamais assignable en masse.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** 2FA activée ET confirmée par un premier code valide. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Page d'accueil après connexion : un administrateur de plateforme SANS entreprise (l'opérateur) arrive dans l'administration ;
     * tous les autres sur le tableau de bord (qui propose de créer une entreprise si besoin).
     * Ne donne aucun accès supplémentaire : seule la destination change.
     */
    public function homeRoute(): string
    {
        return $this->is_platform_admin && ! $this->companies()->exists() ? 'admin.dashboard' : 'dashboard';
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'disabled_at' => 'datetime', // jamais assignable en masse (AccountService uniquement)
            // Étape 9 : secret TOTP et codes de secours (hachés) chiffrés au repos ; jamais assignables en masse.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
