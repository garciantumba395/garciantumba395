<?php

namespace App\Domain\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Désactivation/réactivation d'entreprises et de comptes, et attribution du statut d'administrateur de plateforme.
 * Le contrôle d'AUTORISATION (est-on administrateur ?) est fait par l'appelant (composant Livewire / commande console) ;
 * ce service impose les règles métier : motif obligatoire, pas d'auto-désactivation, jamais le dernier administrateur.
 */
class AccountService
{
    public function __construct(private AuditLogger $audit) {}

    public function disableCompany(Company $company, string $reason): void
    {
        $reason = $this->reason($reason);
        if ($company->is_active) {
            $company->forceFill(['is_active' => false])->save();
            $this->audit->record('company.disabled', $company, $company, ['reason' => $reason]);
            // Les envois en cours s'arrêtent (pause, reprise manuelle possible après réactivation) ; les campagnes programmées ne démarrent plus.
            $campaigns = app(CampaignService::class);
            Campaign::withoutGlobalScopes()->where('company_id', $company->id)
                ->whereIn('status', [CampaignStatus::Preparing->value, CampaignStatus::Sending->value])->get()
                ->each(fn (Campaign $c) => $campaigns->pause($c, 'company_disabled', 'Entreprise désactivée par l\'administration.'));
        }
    }

    public function enableCompany(Company $company, string $reason): void
    {
        $reason = $this->reason($reason);
        if (! $company->is_active) {
            $company->forceFill(['is_active' => true])->save();
            $this->audit->record('company.enabled', $company, $company, ['reason' => $reason]);
        }
    }

    public function disableUser(User $target, User $actor, string $reason): void
    {
        $reason = $this->reason($reason);
        if ($target->is($actor)) {
            throw new InvalidArgumentException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        DB::transaction(function () use ($target, $reason) {
            $fresh = User::query()->lockForUpdate()->findOrFail($target->id);
            if ($fresh->disabled_at !== null) {
                return;
            }
            if ($fresh->is_platform_admin && $this->activeAdminCount() <= 1) {
                throw new InvalidArgumentException('Impossible de désactiver le dernier administrateur de la plateforme.');
            }
            $fresh->forceFill(['disabled_at' => now(), 'disabled_reason' => $reason])->save();
            // Invalide les sessions « base de données » ouvertes (les autres pilotes sont couverts par le middleware).
            DB::table('sessions')->where('user_id', $fresh->id)->delete();
            $fresh->forceFill(['remember_token' => null])->save();
            $this->audit->record('user.disabled', null, $fresh, ['reason' => $reason]);
        });
    }

    public function enableUser(User $target, string $reason): void
    {
        $reason = $this->reason($reason);
        if ($target->disabled_at !== null) {
            $target->forceFill(['disabled_at' => null, 'disabled_reason' => null])->save();
            $this->audit->record('user.enabled', null, $target, ['reason' => $reason]);
        }
    }

    /** Console uniquement. */
    public function grantPlatformAdmin(User $user, ?int $actorId = null): bool
    {
        if ($user->disabled_at !== null) {
            throw new InvalidArgumentException('Ce compte est désactivé.');
        }
        if ($user->is_platform_admin) {
            return false;
        }
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->audit->recordAs($actorId, 'platform_admin.granted', null, $user, ['via' => 'console']);

        return true;
    }

    /** Console uniquement. Refuse de retirer le dernier administrateur. */
    public function revokePlatformAdmin(User $user, ?int $actorId = null): bool
    {
        return DB::transaction(function () use ($user, $actorId) {
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $fresh->is_platform_admin) {
                return false;
            }
            if ($this->activeAdminCount() <= 1) {
                throw new InvalidArgumentException('Impossible de retirer le dernier administrateur de la plateforme.');
            }
            $fresh->forceFill(['is_platform_admin' => false])->save();
            $this->audit->recordAs($actorId, 'platform_admin.revoked', null, $fresh, ['via' => 'console']);

            return true;
        });
    }

    /** Administrateurs de plateforme dont le compte est actif. */
    public function activeAdminCount(): int
    {
        return User::where('is_platform_admin', true)->whereNull('disabled_at')->count();
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Un motif est obligatoire.');
        }

        return mb_substr($reason, 0, 255);
    }
}
