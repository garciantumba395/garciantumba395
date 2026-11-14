<?php

namespace App\Console\Commands;

use App\Domain\Admin\AccountService;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Seule voie d'attribution du statut d'administrateur de la plateforme (jamais depuis l'interface web). */
class GrantPlatformAdmin extends Command
{
    protected $signature = 'platform:grant-admin {email : E-mail du compte existant}';

    protected $description = 'Donne le statut d\'administrateur de la plateforme à un compte existant (journalisé)';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Aucun compte avec cet e-mail.');

            return self::FAILURE;
        }
        try {
            $changed = $accounts->grantPlatformAdmin($user);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $changed ? $this->info('Administrateur de plateforme attribué.') : $this->line('Ce compte est déjà administrateur : rien à faire.');

        return self::SUCCESS;
    }
}
