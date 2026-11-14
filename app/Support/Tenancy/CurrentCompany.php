<?php

namespace App\Support\Tenancy;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Entreprise active de la requête. L'identifiant en session n'est JAMAIS cru
 * tel quel : il est revérifié contre les adhésions de l'utilisateur connecté.
 */
class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    private ?Company $company = null;

    private bool $resolved = false;

    private bool $forced = false;

    private int|string|null $resolvedFor = null;

    private mixed $resolvedSession = null;

    private ?CompanyRole $role = null;

    public function company(): ?Company
    {
        // Cache invalidé si l'utilisateur ou l'entreprise en session a changé (sécurité en cas de processus longue durée).
        if (! $this->forced && (! $this->resolved
            || $this->resolvedFor !== Auth::id()
            || $this->resolvedSession !== session(self::SESSION_KEY))) {
            $this->resolve();
        }

        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company()?->id;
    }

    public function role(): ?CompanyRole
    {
        $this->company();

        return $this->role;
    }

    /** Change d'entreprise ; false si l'utilisateur n'en est pas membre actif. */
    public function switchTo(User $user, int $companyId): bool
    {
        $membership = $this->membership($user, $companyId);
        if (! $membership) {
            return false;
        }
        session([self::SESSION_KEY => $companyId]);
        $this->forget();

        return true;
    }

    /** Force le contexte (jobs, tests, console) — pas d'accès via la session. */
    public function set(?Company $company, ?CompanyRole $role = null): void
    {
        $this->company = $company;
        $this->role = $role;
        $this->resolved = true;
        $this->forced = $company !== null;
    }

    public function forget(): void
    {
        $this->company = null;
        $this->role = null;
        $this->resolved = false;
        $this->forced = false;
    }

    private function resolve(): void
    {
        $this->resolved = true;
        $this->company = null;
        $this->role = null;
        $this->resolvedFor = Auth::id();
        $this->resolvedSession = session(self::SESSION_KEY);
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $wanted = session(self::SESSION_KEY);
        $membership = $wanted ? $this->membership($user, (int) $wanted) : null;

        $membership ??= $user->companies()->where('companies.is_active', true)
            ->orderBy('companies.id')->first();

        if ($membership) {
            $this->company = $membership;
            $this->role = CompanyRole::from($membership->pivot->role);
            session([self::SESSION_KEY => $membership->id]);
            $this->resolvedSession = $membership->id;
        }
    }

    private function membership(User $user, int $companyId): ?Company
    {
        return $user->companies()
            ->where('companies.id', $companyId)
            ->where('companies.is_active', true)
            ->first();
    }
}
