<?php

namespace App\Console\Commands;

use App\Domain\Admin\AccountService;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Retire le statut d'administrateur de la plateforme ; refuse de retirer le dernier. */
class RevokePlatformAdmin extends Command
{
    protected $signature = 'platform:revoke-admin {email : E-mail du compte}';

    protected $description = 'Retire le statut d\'administrateur de la plateforme (refusé pour le dernier administrateur ; journalisé)';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Aucun compte avec cet e-mail.');

            return self::FAILURE;
        }
        try {
            $changed = $accounts->revokePlatformAdmin($user);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $changed ? $this->info('Statut d\'administrateur retiré.') : $this->line('Ce compte n\'est pas administrateur : rien à faire.');

        return self::SUCCESS;
    }
}
