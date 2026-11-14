<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Règle « aucun secret ni donnée client dans le dépôt », rendue exécutable : échoue si un dump, une base de données,
 * un .env, une clé privée ou une APP_KEY réelle devient suivi par Git. (Un dump Redis vide a déjà été commité par erreur.)
 */
class RepositoryHygieneTest extends TestCase
{
    /** @return array<int,string> */
    private function trackedFiles(): array
    {
        $root = base_path();
        $out = shell_exec('git -C '.escapeshellarg($root).' ls-files -z 2>/dev/null');
        if ($out === null || $out === '' || ! is_dir($root.'/.git') && ! is_file($root.'/.git')) {
            $this->markTestSkipped('Dépôt Git indisponible.');
        }

        return array_values(array_filter(explode("\0", $out)));
    }

    public function test_no_dump_database_env_or_key_file_is_tracked(): void
    {
        $forbidden = '~(\.(rdb|sqlite3?|db|sql|dump|bak|pem|key|p12|pfx|keystore|jks|zip|tar|gz|tgz)$|(^|/)\.env($|\.(?!example$))|(^|/)(node_modules|vendor)/|^storage/(logs|framework/(cache|sessions|views))/(?!(.*/)?\.gitignore$))~i';

        $bad = array_values(array_filter($this->trackedFiles(), fn ($f) => preg_match($forbidden, $f)));

        $this->assertSame([], $bad, 'Fichiers qui ne doivent jamais être versionnés : '.implode(', ', $bad));
    }

    public function test_no_private_key_or_real_app_key_is_committed(): void
    {
        $leaks = [];
        foreach ($this->trackedFiles() as $file) {
            $path = base_path($file);
            if (! is_file($path) || filesize($path) > 1_500_000 || preg_match('~\.(png|jpg|jpeg|gif|ico|woff2?|ttf|pdf)$~i', $file)) {
                continue;
            }
            $text = (string) file_get_contents($path);
            if (preg_match('~-----BEGIN (RSA |EC |OPENSSH |DSA |ENCRYPTED )?PRIVATE KEY-----~', $text) && ! str_starts_with($file, 'tests/')) {
                $leaks[] = "$file (clé privée)";
            }
            if (! str_starts_with($file, 'tests/') && preg_match('~^APP_KEY=base64:[A-Za-z0-9+/]{30,}={0,2}\s*$~m', $text)) {
                $leaks[] = "$file (APP_KEY réelle)";
            }
        }

        $this->assertSame([], $leaks, 'Secrets détectés : '.implode(', ', $leaks));
    }

    public function test_env_example_contains_no_secret_values(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'MAIL_PASSWORD', 'SMS_WEBHOOK_FAKE_SECRET'] as $var) {
            preg_match('~^'.$var.'=(.*)$~m', $example, $m);
            $value = trim($m[1] ?? '', " \t\"'");
            $this->assertTrue(in_array($value, ['', 'null'], true), "$var doit être vide dans .env.example (valeur trouvée : une valeur non vide)");
        }
    }

    public function test_the_guard_actually_detects_a_forbidden_file(): void
    {
        $forbidden = '~(\.(rdb|sqlite3?|db|sql|dump|bak|pem|key)$|(^|/)\.env($|\.(?!example$)))~i';

        foreach (['dump.rdb', 'database/database.sqlite', '.env', '.env.production', 'secrets/server.pem'] as $f) {
            $this->assertSame(1, preg_match($forbidden, $f), "$f devrait être refusé");
        }
        foreach ([' .env.example', 'README.md', 'app/Models/User.php'] as $f) {
            $this->assertSame(0, preg_match($forbidden, trim($f)), "$f ne devrait pas être refusé");
        }
    }
}
