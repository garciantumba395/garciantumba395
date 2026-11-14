<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Contrôle de configuration avant mise en production (LECTURE SEULE : ne modifie rien, aucun appel réseau).
 * Sortie : seulement des NOMS de réglages et des états (« vide », « activé »), jamais une valeur secrète
 * (clé d'application, mot de passe de base, clés d'API).
 * Code de sortie ≠ 0 s'il y a au moins un problème bloquant (ou un avertissement avec --strict).
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check {--strict : Traite aussi les avertissements comme bloquants}';

    protected $description = 'Signale les réglages dangereux pour la production (lecture seule, sans afficher de secret)';

    /** @var list<array{0:string,1:string,2:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->results = [];
        $this->checkApplication();
        $this->checkSession();
        $this->checkSms();
        $this->checkDatabase();
        $this->checkInfrastructure();
        $this->checkAccountSecurity();

        $labels = ['ok' => 'OK', 'info' => 'INFO', 'warn' => 'AVERT.', 'error' => 'ERREUR'];
        foreach ($this->results as [$level, $name, $message]) {
            $line = "[{$labels[$level]}] $name : $message";
            match ($level) {
                'error' => $this->error($line),
                'warn' => $this->warn($line),
                default => $this->line($line),
            };
        }

        $errors = count(array_filter($this->results, fn ($r) => $r[0] === 'error'));
        $warnings = count(array_filter($this->results, fn ($r) => $r[0] === 'warn'));
        $blocking = $errors + ($this->option('strict') ? $warnings : 0);
        $this->newLine();
        $blocking > 0
            ? $this->error("$errors problème(s) bloquant(s), $warnings avertissement(s).")
            : $this->info("Aucun problème bloquant ($warnings avertissement(s)).");

        return $blocking > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkApplication(): void
    {
        $this->add(empty(config('app.key')) ? 'error' : 'ok', 'APP_KEY', empty(config('app.key')) ? 'absente (chiffrement des sessions, du secret 2FA et des cookies impossible)' : 'définie');
        $this->add(config('app.debug') ? 'error' : 'ok', 'APP_DEBUG', config('app.debug') ? 'activé : expose des traces et des variables d\'environnement' : 'désactivé');
        $production = config('app.env') === 'production';
        $this->add($production ? 'ok' : 'warn', 'APP_ENV', $production ? 'production' : 'n\'est pas « production »');
        if ($production && config('logging.channels.single.level', 'debug') === 'debug') {
            $this->add('warn', 'LOG_LEVEL', 'debug en production : journaux très verbeux (risque de données dans les journaux)');
        }
        $rounds = (int) config('hashing.bcrypt.rounds', 12);
        $this->add($rounds < 10 ? 'warn' : 'ok', 'BCRYPT_ROUNDS', $rounds < 10 ? 'inférieur à 10' : 'suffisant');
    }

    private function checkSession(): void
    {
        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->add($https ? 'ok' : 'warn', 'APP_URL', $https ? 'HTTPS' : 'n\'est pas en HTTPS (HSTS et cookies sécurisés inapplicables)');

        if ($https && config('session.secure') !== true) {
            $this->add('error', 'SESSION_SECURE_COOKIE', 'doit être true derrière HTTPS : le cookie de session pourrait transiter en clair');
        } else {
            $this->add('ok', 'SESSION_SECURE_COOKIE', config('session.secure') === true ? 'activé' : 'non requis (APP_URL n\'est pas en HTTPS)');
        }
        $this->add(config('session.http_only') ? 'ok' : 'error', 'SESSION_HTTP_ONLY', config('session.http_only') ? 'activé' : 'désactivé : le cookie de session est lisible par JavaScript');
        if (strtolower((string) config('session.same_site')) === 'none') {
            $this->add('warn', 'SESSION_SAME_SITE', 'none : cookie envoyé depuis d\'autres sites (risque CSRF accru)');
        }
        if (in_array(config('session.driver'), ['array', 'cookie'], true)) {
            $this->add('warn', 'SESSION_DRIVER', 'pilote non adapté à la production (sessions perdues ou stockées côté client)');
        }
    }

    private function checkSms(): void
    {
        $enabled = (bool) config('sms.sending_enabled');
        $fake = config('sms.provider') === 'fake';
        if ($enabled && $fake) {
            $this->add('error', 'SMS_SENDING_ENABLED', 'activé alors que SMS_PROVIDER est le fournisseur fictif : configuration incohérente (aucun SMS ne partirait, mais les statistiques seraient faussées)');
        } elseif ($enabled) {
            $this->add('warn', 'SMS_SENDING_ENABLED', 'activé avec un fournisseur réel : les envois sont RÉELS (vérifiez que c\'est voulu)');
        } else {
            $this->add('ok', 'SMS_SENDING_ENABLED', 'désactivé (interrupteur d\'urgence engagé : aucun envoi réel)');
        }
    }

    private function checkDatabase(): void
    {
        $name = config('database.default');
        $conn = config("database.connections.$name", []);
        if (($conn['driver'] ?? null) === 'sqlite') {
            $this->add('warn', 'DB_CONNECTION', 'SQLite : réservé aux tests, PostgreSQL attendu en production');

            return;
        }
        $empty = ($conn['password'] ?? '') === '' || $conn['password'] === null;
        $this->add($empty ? 'error' : 'ok', 'DB_PASSWORD', $empty ? 'vide' : 'renseigné');
    }

    private function checkInfrastructure(): void
    {
        $production = config('app.env') === 'production';
        if (config('queue.default') === 'sync') {
            $this->add($production ? 'warn' : 'info', 'QUEUE_CONNECTION', 'sync : les envois ne passent pas par la file Redis (aucun worker)');
        }
        if (config('cache.default') === 'array') {
            $this->add($production ? 'warn' : 'info', 'CACHE_STORE', 'array : la limitation de débit n\'est pas partagée entre processus');
        }
        if ($production && in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->add('warn', 'MAIL_MAILER', 'log/array : les e-mails de réinitialisation de mot de passe ne partiront pas');
        }
    }

    private function checkAccountSecurity(): void
    {
        $this->add(config('security.require_2fa_for_admins') ? 'ok' : 'warn', 'SECURITY_REQUIRE_2FA_FOR_ADMINS', config('security.require_2fa_for_admins') ? '2FA obligatoire pour les administrateurs' : '2FA NON imposée aux administrateurs (décision D10 : obligatoire)');
        $this->add(config('security.require_email_verification') ? 'ok' : 'warn', 'SECURITY_REQUIRE_EMAIL_VERIFICATION', config('security.require_email_verification') ? 'vérification de l\'e-mail obligatoire' : 'vérification de l\'e-mail NON imposée (décision D10 : obligatoire)');
        // Une vérification obligatoire sans envoi d'e-mails réel enferme tous les nouveaux comptes (hors commande users:verify-email).
        if (config('security.require_email_verification') && config('app.env') === 'production' && in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->add('error', 'MAIL_MAILER', 'vérification d\'e-mail obligatoire mais MAIL_MAILER=log/array : aucun lien ne sera reçu (configurez un envoi réel)');
        }
        $this->add(config('security.headers.hsts') ? 'ok' : 'warn', 'SECURITY_HSTS', config('security.headers.hsts') ? 'activé (appliqué uniquement en HTTPS)' : 'désactivé');
    }

    private function add(string $level, string $name, string $message): void
    {
        $this->results[] = [$level, $name, $message];
    }
}
