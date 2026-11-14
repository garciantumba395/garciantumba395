<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityCheckCommandTest extends TestCase
{
    private const SECRET_KEY = 'base64:Uy3GEHEIMERSCHLUESSEL0123456789abcdefghijk=';

    private const SECRET_DB = 'MotDePasseBaseTresSecret!42';

    private const SECRET_MAIL = 'MotDePasseMailSecret#7';

    /** Configuration saine pour la production (PostgreSQL fictif : aucune connexion n'est ouverte). */
    private function healthy(): void
    {
        config([
            'app.key' => self::SECRET_KEY,
            'app.debug' => false,
            'app.env' => 'production',
            'app.url' => 'https://sms.example.test',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'redis',
            'sms.provider' => 'fake',
            'sms.sending_enabled' => false,
            'database.default' => 'pgsql',
            'database.connections.pgsql.password' => self::SECRET_DB,
            'mail.mailers.smtp.password' => self::SECRET_MAIL,
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'mail.default' => 'smtp',
            'logging.channels.single.level' => 'warning',
            'hashing.bcrypt.rounds' => 12,
        ]);
    }

    private function runCheck(array $options = []): array
    {
        $code = Artisan::call('security:check', $options);

        return [$code, Artisan::output()];
    }

    public function test_healthy_production_configuration_passes(): void
    {
        $this->healthy();
        [$code, $out] = $this->runCheck();

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Aucun problème bloquant', $out);
        $this->assertStringNotContainsString('ERREUR', $out);
    }

    public function test_dangerous_settings_are_blocking(): void
    {
        $this->healthy();
        config(['app.debug' => true, 'app.key' => '', 'sms.sending_enabled' => true, 'sms.provider' => 'fake',
            'session.secure' => null, 'database.connections.pgsql.password' => '']);
        [$code, $out] = $this->runCheck();

        $this->assertSame(1, $code);
        foreach (['APP_KEY', 'APP_DEBUG', 'SMS_SENDING_ENABLED', 'SESSION_SECURE_COOKIE', 'DB_PASSWORD'] as $name) {
            $this->assertMatchesRegularExpression('/ERREUR\] '.$name.' /', $out, "$name devrait être bloquant");
        }
        $this->assertStringContainsString('5 problème(s) bloquant(s)', $out);
    }

    public function test_each_blocking_setting_alone_fails(): void
    {
        $cases = [
            'APP_DEBUG' => ['app.debug' => true],
            'APP_KEY' => ['app.key' => ''],
            'SESSION_SECURE_COOKIE' => ['session.secure' => false],
            'SESSION_HTTP_ONLY' => ['session.http_only' => false],
            'SMS_SENDING_ENABLED' => ['sms.sending_enabled' => true],
            'DB_PASSWORD' => ['database.connections.pgsql.password' => null],
        ];
        foreach ($cases as $name => $override) {
            $this->healthy();
            config($override);
            [$code, $out] = $this->runCheck();
            $this->assertSame(1, $code, "$name devrait faire échouer la commande");
            $this->assertStringContainsString("$name ", $out);
        }
    }

    public function test_real_provider_with_sending_enabled_is_only_a_warning(): void
    {
        $this->healthy();
        config(['sms.provider' => 'un-fournisseur-reel', 'sms.sending_enabled' => true]);
        [$code, $out] = $this->runCheck();

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/AVERT\.\] SMS_SENDING_ENABLED/', $out);
    }

    public function test_warnings_do_not_fail_unless_strict(): void
    {
        $this->healthy();
        config(['app.env' => 'staging', 'app.url' => 'http://sms.example.test', 'session.secure' => false]);

        [$code, $out] = $this->runCheck();
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('AVERT.', $out);

        [$strict] = $this->runCheck(['--strict' => true]);
        $this->assertSame(1, $strict);
    }

    public function test_output_never_leaks_secret_values(): void
    {
        foreach ([true, false] as $dangerous) {
            $this->healthy();
            if ($dangerous) {
                config(['app.debug' => true, 'sms.sending_enabled' => true, 'session.secure' => null]);
            }
            [, $out] = $this->runCheck(['--strict' => true]);

            foreach ([self::SECRET_KEY, self::SECRET_DB, self::SECRET_MAIL, 'Uy3GEHEIMER', 'base64:'] as $secret) {
                $this->assertStringNotContainsString($secret, $out);
            }
        }
    }

    public function test_command_is_read_only(): void
    {
        $this->healthy();
        DB::enableQueryLog();
        $this->runCheck();
        $this->assertSame([], DB::getQueryLog(), 'security:check ne doit exécuter aucune requête SQL');
    }

    public function test_sqlite_is_flagged_as_test_only(): void
    {
        $this->healthy();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.driver' => 'sqlite']);
        [, $out] = $this->runCheck();
        $this->assertMatchesRegularExpression('/AVERT\.\] DB_CONNECTION/', $out);
    }
}
