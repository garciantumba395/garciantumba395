<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Voie de secours pour l'exploitant (ex. premier administrateur avant que l'envoi d'e-mails soit configuré, ou boîte inaccessible).
 * Console uniquement, journalisée. N'est jamais exposée par l'interface web.
 */
class VerifyUserEmail extends Command
{
    protected $signature = 'users:verify-email {email : E-mail du compte existant}';

    protected $description = 'Marque l\'adresse e-mail d\'un compte comme vérifiée (console uniquement, journalisé)';

    public function handle(AuditLogger $audit): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Aucun compte avec cet e-mail.');

            return self::FAILURE;
        }
        if ($user->hasVerifiedEmail()) {
            $this->line('Adresse déjà vérifiée : rien à faire.');

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();
        $audit->recordAs(null, 'user.email_verified', null, $user, ['method' => 'console']);
        $this->info('Adresse e-mail marquée comme vérifiée.');

        return self::SUCCESS;
    }
}
