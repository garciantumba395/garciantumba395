<?php

namespace App\Console\Commands;

use App\Domain\Sms\SmsStatus;
use App\Models\Company;
use App\Models\SmsMessage;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Réconciliation en LECTURE SEULE : liste les messages restés « en cours » (sending) ou « résultat inconnu » (unknown) depuis
 * plus d'un seuil. N'écrit rien, ne renvoie rien, ne rembourse rien (la résolution automatique dépend de la décision D13).
 * Les numéros sont masqués, le contenu des messages n'est jamais affiché. Seuls les envois RÉELS comptent pour le code de sortie ;
 * les messages simulés ne sont affichés que sur demande (--include-simulated), dans une section séparée.
 *
 * Code de sortie : 0 = rien à réconcilier ; 1 = au moins un message réel en attente de résolution ; 2 = option invalide.
 */
class ReconcileSms extends Command
{
    protected $signature = 'sms:reconcile
        {--minutes= : Seuil en minutes (défaut : config sms.reconcile.stale_minutes)}
        {--company= : Limiter à une entreprise (identifiant)}
        {--limit=50 : Nombre maximum de lignes affichées par entreprise}
        {--include-simulated : Afficher aussi les messages simulés (section séparée, sans effet sur le code de sortie)}';

    protected $description = 'Liste (lecture seule) les messages bloqués en « en cours » ou « résultat inconnu » au-delà d\'un seuil';

    public function handle(CurrentCompany $current): int
    {
        $minutes = $this->option('minutes') ?? config('sms.reconcile.stale_minutes');
        $limit = $this->option('limit');
        if (! ctype_digit((string) $minutes) || (int) $minutes < 1 || ! ctype_digit((string) $limit) || (int) $limit < 1
            || ($this->option('company') !== null && ! ctype_digit((string) $this->option('company')))) {
            $this->error('Options invalides : --minutes, --limit et --company doivent être des entiers positifs.');

            return 2;
        }
        $cutoff = now()->subMinutes((int) $minutes);

        $companies = Company::orderBy('id');
        if ($this->option('company') !== null) {
            $companies->whereKey((int) $this->option('company'));
        }

        $realTotal = 0;
        foreach ($companies->get() as $company) {
            // Contexte d'entreprise explicite : les requêtes ci-dessous passent par le scope d'isolation.
            $current->runAs($company, function () use ($company, $cutoff, $limit, &$realTotal) {
                $realTotal += $this->report($company, false, $cutoff, (int) $limit);
                if ($this->option('include-simulated')) {
                    $this->report($company, true, $cutoff, (int) $limit);
                }
            });
        }

        if ($realTotal === 0) {
            $this->info("OK : aucun message réel en attente de réconciliation depuis plus de {$minutes} minute(s).");

            return self::SUCCESS;
        }
        $this->error("{$realTotal} message(s) réel(s) à réconcilier manuellement (voir docs/PROVIDERS.md, « Réconciliation manuelle »).");

        return self::FAILURE;
    }

    private function report(Company $company, bool $simulated, $cutoff, int $limit): int
    {
        $query = SmsMessage::where('simulated', $simulated)
            ->whereIn('status', [SmsStatus::Sending->value, SmsStatus::Unknown->value])
            ->where('updated_at', '<=', $cutoff);

        $count = (clone $query)->count();
        if ($count === 0) {
            return 0;
        }

        $label = $simulated ? 'SIMULÉS' : 'RÉELS';
        $this->line("Entreprise #{$company->id} — {$count} message(s) {$label}");
        $rows = $query->orderBy('updated_at')->orderBy('id')->limit($limit)->get()->map(fn (SmsMessage $m) => [
            $m->id,
            $m->status->value,
            $m->provider ?? '-',
            self::maskPhone($m->phone),
            $m->updated_at->format('Y-m-d H:i'),
            (int) $m->updated_at->diffInMinutes(now(), true),
            $m->error_code ?? '-',
        ])->all();
        $this->table(['Message', 'Statut', 'Fournisseur', 'Numéro', 'Depuis', 'Minutes', 'Code'], $rows);
        if ($count > $limit) {
            $this->line('… '.($count - $limit).' autre(s) non affiché(s) (--limit).');
        }

        return $count;
    }

    /** Garde l'indicatif d'entrée (+ et 3 premiers chiffres) et les 2 derniers chiffres : jamais le numéro complet. */
    public static function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 4).str_repeat('*', $length - 6).substr($phone, -2);
    }
}
