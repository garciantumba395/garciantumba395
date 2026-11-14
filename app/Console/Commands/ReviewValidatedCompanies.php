<?php

namespace App\Console\Commands;

use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Surveillance continue : réévalue chaque entreprise « validée » sur la fenêtre glissante des envois réels (planifié chaque jour). */
class ReviewValidatedCompanies extends Command
{
    protected $signature = 'sending:review';

    protected $description = 'Suspend automatiquement les entreprises validées dont les envois réels récents dépassent les seuils d\'échec ou de désinscription';

    public function handle(SendingPolicy $policy): int
    {
        $reviewed = 0;
        $suspended = [];
        // Company n'est pas scopée par entreprise ; SendingPolicy filtre explicitement par company_id (jamais de données d'une autre entreprise).
        Company::where('sending_status', SendingStatus::Validated->value)->orderBy('id')->pluck('id')->each(function ($id) use ($policy, &$reviewed, &$suspended) {
            $reviewed++;
            if ($policy->reviewValidated((int) $id)) {
                $suspended[] = (int) $id;
            }
        });

        if ($suspended !== []) {
            // Identifiants seulement : aucun nom, numéro ni contenu de message.
            Log::warning('sending:review : entreprise(s) validée(s) suspendue(s) automatiquement.', ['company_ids' => $suspended]);
        }
        $this->info(sprintf('%d entreprise(s) validée(s) examinée(s), %d suspendue(s).', $reviewed, count($suspended)));

        return self::SUCCESS;
    }
}
