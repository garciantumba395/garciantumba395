<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CreditLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Contrôle de cohérence (lecture seule) : le solde de chaque entreprise doit égaler la somme de son grand livre. */
class VerifyCredits extends Command
{
    protected $signature = 'credits:verify';

    protected $description = 'Vérifie que le solde de chaque entreprise correspond à son grand livre de crédits (lecture seule)';

    public function handle(): int
    {
        $bad = 0;
        foreach (Company::orderBy('id')->get() as $company) {
            $sum = (int) CreditLedgerEntry::withoutGlobalScopes()->where('company_id', $company->id)->sum('amount_units');
            if ($sum !== (int) $company->credit_balance_units) {
                $bad++;
                $this->error("Incohérence — entreprise #{$company->id} : solde {$company->credit_balance_units}, grand livre {$sum}");
            }
        }
        if ($bad > 0) {
            // Journalisé (surveillance) : nombre seulement, aucun solde ni identifiant dans le journal.
            Log::error("credits:verify : $bad incohérence(s) entre solde et grand livre de crédits.");
        }
        $bad === 0 ? $this->info('OK : tous les soldes correspondent au grand livre.') : $this->error("$bad entreprise(s) incohérente(s).");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
