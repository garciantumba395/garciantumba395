<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** D10 (tranchée : oui) — l'adresse e-mail doit être vérifiée avant tout accès à l'application. */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverified(bool $company = true, bool $admin = false): User
    {
        $u = User::factory()->unverified()->create(['password' => 'mot-de-passe-solide-1']);
        if ($company) {
            Company::createFor($u, 'Acme');
        }
        if ($admin) {
            $u->forceFill(['is_platform_admin' => true])->save();
        }

        return $u;
    }

    private function link(User $u, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $u->id, 'hash' => $hash ?? sha1($u->getEmailForVerification())]);
    }

    // --- Inscription ---------------------------------------------------------------

    public function test_registration_sends_a_verification_link_and_lands_on_the_notice(): void
    {
        Notification::fake();

        Livewire::test(Register::class)->set('name', 'Jean')->set('email', 'jean@example.test')->set('company_name', 'Acme')
            ->set('password', 'mot-de-passe-solide-1')->set('password_confirmation', 'mot-de-passe-solide-1')
            ->call('register')->assertRedirect(route('verification.notice'));

        $user = User::firstWhere('email', 'jean@example.test');
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // --- Blocage tant que non vérifié ------------------------------------------------

    public function test_unverified_user_can_log_in_but_reaches_only_the_notice(): void
    {
        $u = $this->unverified();

        Livewire::test(Login::class)->set('email', $u->email)->set('password', 'mot-de-passe-solide-1')->call('login');
        $this->assertAuthenticatedAs($u);

        $this->get('/verification-email')->assertOk()->assertSee($u->email);
    }

    public function test_every_application_page_redirects_an_unverified_user_to_the_notice(): void
    {
        $this->actingAs($this->unverified());

        foreach (['/tableau-de-bord', '/contacts', '/contacts/importer', '/groupes', '/campagnes', '/messages', '/credits', '/rapports', '/rapports/export',
            '/entreprise/parametres', '/entreprise/membres', '/desinscriptions', '/profil', '/entreprises/nouvelle'] as $url) {
            $this->get($url)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_unverified_platform_admin_is_blocked_too(): void
    {
        $this->actingAs($this->unverified(company: false, admin: true));

        foreach (['/admin', '/admin/entreprises', '/admin/audit', '/admin/utilisateurs'] as $url) {
            $this->get($url)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_livewire_style_requests_from_an_unverified_user_are_refused_with_403(): void
    {
        $this->actingAs($this->unverified());

        $this->withHeaders(['X-Livewire' => 'true'])->get('/contacts')->assertForbidden();
        $this->getJson('/contacts')->assertForbidden();
    }

    public function test_logout_remains_possible_while_unverified(): void
    {
        $this->actingAs($this->unverified());

        $this->post('/deconnexion')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_verified_user_is_not_affected(): void
    {
        $u = User::factory()->create();
        Company::createFor($u, 'Acme');

        $this->actingAs($u)->get('/tableau-de-bord')->assertOk();
        $this->get('/verification-email')->assertRedirect(route('dashboard'));
    }

    public function test_the_requirement_can_be_switched_off_by_configuration(): void
    {
        config(['security.require_email_verification' => false]);
        $this->actingAs($this->unverified())->get('/tableau-de-bord')->assertOk();
    }

    /** Page ouverte, puis requête de mise à jour Livewire brute (vrai snapshot extrait du HTML). */
    private function livewireUpdateFromOpenPage(bool $required): TestResponse
    {
        config(['security.require_email_verification' => false]);
        $this->actingAs($this->unverified());
        $html = $this->get(route('contacts.index'))->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = html_entity_decode($m[1], ENT_QUOTES);
        $this->assertNotSame('', $snapshot);

        config(['security.require_email_verification' => $required]); // exigée APRÈS l'ouverture de la page

        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ]);
    }

    public function test_livewire_update_is_accepted_when_verification_is_not_required(): void
    {
        $this->livewireUpdateFromOpenPage(false)->assertOk();
    }

    public function test_requirement_also_blocks_livewire_updates_of_an_already_open_page(): void
    {
        $this->livewireUpdateFromOpenPage(true)->assertForbidden();
    }

    // --- Lien de vérification ---------------------------------------------------------

    public function test_valid_signed_link_verifies_the_address_and_lands_on_the_home_page(): void
    {
        $u = $this->unverified();

        $this->actingAs($u)->get($this->link($u))->assertRedirect(route('dashboard'));

        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        $this->get('/tableau-de-bord')->assertOk();
    }

    public function test_company_less_platform_admin_lands_in_administration_after_verifying(): void
    {
        $u = $this->unverified(company: false, admin: true);

        $this->actingAs($u)->get($this->link($u))->assertRedirect(route('admin.dashboard'));
    }

    public function test_tampered_unsigned_wrong_hash_or_foreign_links_are_refused(): void
    {
        $u = $this->unverified();
        $other = $this->unverified(company: false);
        $other->forceFill(['email' => 'autre@example.test'])->save();
        $this->actingAs($u);

        $this->get($this->link($u, sha1('mauvais@example.test')))->assertForbidden();       // empreinte de e-mail incorrecte
        $this->get('/verification-email/'.$u->id.'/'.sha1($u->email))->assertForbidden();  // lien non signé
        $this->get($this->link($other))->assertForbidden();                                 // lien d'un autre compte
        $this->get(str_replace('signature=', 'signature=x', $this->link($u)))->assertForbidden(); // signature altérée

        $this->assertFalse($u->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_is_refused(): void
    {
        $u = $this->unverified();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $u->id, 'hash' => sha1($u->email)]);

        $this->actingAs($u)->get($url)->assertForbidden();
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_link_requires_to_be_signed_in_and_works_after_login(): void
    {
        $u = $this->unverified();
        $url = $this->link($u);

        $this->get($url)->assertRedirect(route('login'));
        $this->assertFalse($u->fresh()->hasVerifiedEmail());
    }

    public function test_clicking_the_link_twice_is_harmless(): void
    {
        $u = $this->unverified();
        $this->actingAs($u)->get($this->link($u));
        $first = $u->fresh()->email_verified_at;

        $this->get($this->link($u))->assertRedirect(route('dashboard'));

        $this->assertEquals($first, $u->fresh()->email_verified_at);
    }

    // --- Renvoi du lien -----------------------------------------------------------------

    public function test_resend_sends_a_new_link_and_is_rate_limited(): void
    {
        Notification::fake();
        $u = $this->unverified();
        $page = Livewire::actingAs($u)->test(VerifyEmailNotice::class);

        for ($i = 0; $i < 3; $i++) {
            $page->call('resend')->assertHasNoErrors();
        }
        Notification::assertSentToTimes($u, VerifyEmail::class, 3);

        $page->call('resend')->assertHasErrors('resend');
        Notification::assertSentToTimes($u, VerifyEmail::class, 3); // la 4e tentative n'envoie rien
    }

    // --- Autres chemins qui prouvent le contrôle de l'adresse ------------------------------

    public function test_password_reset_through_the_emailed_link_verifies_the_address(): void
    {
        $u = $this->unverified();
        $token = Password::createToken($u);

        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $u->email)
            ->set('password', 'nouveau-mot-de-passe-1')->set('password_confirmation', 'nouveau-mot-de-passe-1')->call('resetPassword');

        $this->assertTrue($u->fresh()->hasVerifiedEmail());
    }

    // --- Courriel en français -----------------------------------------------------------------

    public function test_verification_email_is_written_in_french(): void
    {
        $u = $this->unverified();
        $mail = (new VerifyEmail)->toMail($u);
        $rendered = (string) $mail->render();

        $this->assertSame('Vérifiez votre adresse e-mail', $mail->subject);
        $this->assertStringContainsString('Cliquez sur le bouton ci-dessous', $rendered);
        $this->assertStringContainsString('Cordialement', $rendered);
        $this->assertStringNotContainsString('Verify Email Address', $rendered);
        $this->assertStringNotContainsString('Please click', $rendered);
    }

    public function test_password_reset_email_is_written_in_french(): void
    {
        $u = $this->unverified();
        $mail = (new \Illuminate\Auth\Notifications\ResetPassword('jeton'))->toMail($u);
        $rendered = (string) $mail->render();

        $this->assertSame('Réinitialisation de votre mot de passe', $mail->subject);
        $this->assertStringContainsString('Réinitialiser le mot de passe', $rendered);
        $this->assertStringContainsString('expirera dans', $rendered);
        $this->assertStringNotContainsString('You are receiving', $rendered);
        $this->assertStringNotContainsString('Hello!', $rendered);
    }

    // --- Voie de secours de l'exploitant ----------------------------------------------------------

    public function test_console_command_verifies_an_account_and_is_audited(): void
    {
        $u = $this->unverified();

        $this->artisan('users:verify-email', ['email' => $u->email])->assertSuccessful();

        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.email_verified', 'actor_id' => null]);
        $this->assertStringNotContainsString($u->email, json_encode(AuditLog::where('action', 'user.email_verified')->first()->details));
    }

    public function test_console_command_is_idempotent_and_rejects_unknown_accounts(): void
    {
        $u = User::factory()->create();

        $this->artisan('users:verify-email', ['email' => $u->email])->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.email_verified']);
        $this->artisan('users:verify-email', ['email' => 'inconnu@example.test'])->assertFailed();
    }

    public function test_there_is_no_web_route_that_verifies_without_the_signed_link(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'verification-email'));

        $this->assertSame(['verification-email', 'verification-email/{id}/{hash}'], $routes->map->uri()->sort()->values()->all());
        $this->assertTrue($routes->last()->gatherMiddleware() !== [] && in_array('signed', $routes->firstWhere('uri', 'verification-email/{id}/{hash}')->gatherMiddleware(), true));
    }

    // --- Décision D10 inscrite dans la configuration livrée ---------------------------------------------

    public function test_shipped_defaults_make_both_protections_mandatory(): void
    {
        $config = (string) file_get_contents(base_path('config/security.php'));
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("env('SECURITY_REQUIRE_EMAIL_VERIFICATION', true)", $config);
        $this->assertStringContainsString("env('SECURITY_REQUIRE_2FA_FOR_ADMINS', true)", $config);
        $this->assertStringContainsString("SECURITY_REQUIRE_EMAIL_VERIFICATION=true\n", $example);
        $this->assertSame(1, preg_match_all('/^SECURITY_REQUIRE_2FA_FOR_ADMINS=true$/m', $example));
        $this->assertSame(0, preg_match('/^SECURITY_REQUIRE_2FA_FOR_ADMINS=false$/m', $example)); // pas de doublon contradictoire
    }

    public function test_security_check_blocks_mandatory_verification_without_real_mail_in_production(): void
    {
        config(['app.env' => 'production', 'mail.default' => 'log', 'security.require_email_verification' => true]);

        $this->assertSame(1, Artisan::call('security:check'));
        $this->assertStringContainsString('MAIL_MAILER', Artisan::output());
    }
}
