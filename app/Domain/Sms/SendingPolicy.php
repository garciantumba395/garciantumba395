<?php

namespace App\Domain\Sms;

use App\Domain\Audit\AuditLogger;
use App\Models\Company;
use App\Models\SmsMessage;
use App\Models\Suppression;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Garde-fous de l'envoi RÉEL (D12). Jamais appliqués à la simulation.
 *  - limite journalière (démarrage : réduite ; validée : plafond configurable ou aucun ; surcharge par entreprise) ;
 *  - suspension : plus aucun envoi réel ;
 *  - évaluation automatique : quand l'échantillon d'envois RÉELS est suffisant, passage en « validée » si les indicateurs sont sains,
 *    en « suspendue » sinon. Limite souple : en concurrence, un dépassement de quelques messages est possible.
 */
class SendingPolicy
{
    /** Motif de blocage d'un envoi réel, ou null s'il est autorisé. Appelé uniquement pour un fournisseur réel. */
    public function blockReason(int $companyId, int $excludeMessageId): ?string
    {
        $company = Company::findOrFail($companyId);
        if ($company->sending_status === SendingStatus::Suspended) {
            return 'suspended';
        }

        $limit = $company->daily_send_limit
            ?? ($company->sending_status === SendingStatus::Validated
                ? config('sms.sending.validated_daily_limit')
                : config('sms.sending.probation_daily_limit'));

        if ($limit !== null && $this->sentToday($companyId, $excludeMessageId) >= $limit) {
            return 'daily_limit';
        }

        return null;
    }

    /** Évalue une entreprise en démarrage ; transition automatique si l'échantillon est suffisant. */
    public function evaluate(int $companyId): void
    {
        $company = Company::find($companyId);
        if (! $company || $company->sending_status !== SendingStatus::Probation) {
            return;
        }

        $since = $company->sending_status_changed_at ?? $company->created_at;
        $m = $this->metrics($companyId, $since);
        if ($m['sample'] < config('sms.sending.min_sample')) {
            return;
        }

        if ($m['failure_rate'] > config('sms.sending.max_failure_rate')) {
            $this->transition($company, SendingStatus::Suspended, sprintf('Suspension automatique : taux d\'échec %.0f %% sur %d envois réels.', $m['failure_rate'] * 100, $m['sample']));
        } elseif ($m['optout_rate'] > config('sms.sending.max_optout_rate')) {
            $this->transition($company, SendingStatus::Suspended, sprintf('Suspension automatique : taux de désinscription %.0f %% sur %d envois réels.', $m['optout_rate'] * 100, $m['sample']));
        } else {
            $this->transition($company, SendingStatus::Validated, sprintf('Validation automatique : %d envois réels, échecs %.0f %%, désinscriptions %.0f %%.', $m['sample'], $m['failure_rate'] * 100, $m['optout_rate'] * 100));
        }
    }

    /**
     * Surveillance continue (étape 9, lacune de D12) : suspend une entreprise VALIDÉE dont les envois RÉELS de la fenêtre glissante
     * (security.review.window_days, jamais avant le dernier changement de statut) dépassent les seuils d'échec ou de désinscription,
     * avec un échantillon minimal (security.review.min_sample). Ne touche que les entreprises « validated » ; retourne true si suspendue.
     */
    public function reviewValidated(int $companyId): bool
    {
        $company = Company::find($companyId);
        if (! $company || $company->sending_status !== SendingStatus::Validated) {
            return false;
        }

        $since = now()->subDays(max(1, (int) config('security.review.window_days')));
        if ($company->sending_status_changed_at && $company->sending_status_changed_at->greaterThan($since)) {
            $since = $company->sending_status_changed_at; // les envois antérieurs à la dernière décision ne sont pas rejugés
        }
        $m = $this->metrics($companyId, $since);
        if ($m['sample'] < (int) config('security.review.min_sample')) {
            return false;
        }

        if ($m['failure_rate'] > config('sms.sending.max_failure_rate')) {
            $reason = sprintf('Suspension automatique (surveillance) : taux d\'échec %.0f %% sur %d envois réels récents.', $m['failure_rate'] * 100, $m['sample']);
        } elseif ($m['optout_rate'] > config('sms.sending.max_optout_rate')) {
            $reason = sprintf('Suspension automatique (surveillance) : taux de désinscription %.0f %% sur %d envois réels récents.', $m['optout_rate'] * 100, $m['sample']);
        } else {
            return false;
        }

        // Mise à jour conditionnelle : seule une entreprise encore « validée » est suspendue (pas d'écrasement d'une décision concurrente).
        return DB::table('companies')->where('id', $company->id)->where('sending_status', SendingStatus::Validated->value)->update([
            'sending_status' => SendingStatus::Suspended->value,
            'sending_status_changed_at' => now(),
            'sending_status_reason' => mb_substr($reason, 0, 255),
        ]) === 1;
    }

    /** Changement manuel par l'administrateur de la plateforme (motif obligatoire). */
    public function setStatus(Company $company, SendingStatus $status, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Un motif est obligatoire.');
        }
        $this->transition($company, $status, $reason, manual: true);
    }

    /** @return array{sample:int, failure_rate:float, optout_rate:float} indicateurs calculés sur les envois RÉELS uniquement. */
    public function metrics(int $companyId, $since): array
    {
        $real = SmsMessage::withoutGlobalScopes()->where('company_id', $companyId)->where('simulated', false)
            ->whereIn('status', [SmsStatus::Sent->value, SmsStatus::Delivered->value, SmsStatus::Failed->value])
            ->where('created_at', '>=', $since);

        $sample = (clone $real)->count();
        $failed = (clone $real)->where('status', SmsStatus::Failed->value)->count();

        // Désinscriptions créées depuis le démarrage pour un numéro ayant reçu un envoi réel.
        $optouts = Suppression::withoutGlobalScopes()->where('company_id', $companyId)->where('created_at', '>=', $since)
            ->whereExists(fn ($q) => $q->from('sms_messages')->whereColumn('sms_messages.phone', 'suppressions.phone')
                ->whereColumn('sms_messages.company_id', 'suppressions.company_id')->where('sms_messages.simulated', false))
            ->count();

        return [
            'sample' => $sample,
            'failure_rate' => $sample ? $failed / $sample : 0.0,
            'optout_rate' => $sample ? $optouts / $sample : 0.0,
        ];
    }

    private function sentToday(int $companyId, int $excludeMessageId): int
    {
        return SmsMessage::withoutGlobalScopes()->where('company_id', $companyId)->where('simulated', false)
            ->where('id', '!=', $excludeMessageId)
            ->whereIn('status', [SmsStatus::Sending->value, SmsStatus::Sent->value, SmsStatus::Delivered->value, SmsStatus::Failed->value, SmsStatus::Unknown->value])
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /** $manual = false : transition automatique, acteur « système » dans le journal d'audit. */
    private function transition(Company $company, SendingStatus $status, string $reason, bool $manual = false): void
    {
        $from = $company->sending_status;
        // Mise à jour conditionnelle : deux évaluations concurrentes ne se contredisent pas.
        DB::table('companies')->where('id', $company->id)->update([
            'sending_status' => $status->value,
            'sending_status_changed_at' => now(),
            'sending_status_reason' => mb_substr($reason, 0, 255),
        ]);

        app(AuditLogger::class)->recordAs($manual ? Auth::id() : null, $manual ? 'sending.status_changed' : 'sending.status_auto_changed', $company, $company, [
            'from' => $from->value, 'to' => $status->value, 'reason' => $reason,
        ]);
    }
}
