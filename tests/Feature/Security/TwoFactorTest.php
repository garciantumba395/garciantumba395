<?php

namespace Tests\Feature\Security;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Profile\Show;
use App\Models\Company;
use App\Models\User;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:10'));
        $this->user = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($this->user, 'Acme');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Code TOTP valide pour le secret de l'utilisateur, à $offset pas du moment présent. */
    private function code(?User $user = null, int $offset = 0): string
    {
        $user ??= $this->user;

        return Totp::at($user->fresh()->two_factor_secret, now()->timestamp + $offset * 30);
    }

    private function later(int $seconds = 30): void
    {
        Carbon::setTestNow(now()->addSeconds($seconds));
    }

    /** Active la 2FA via le service ; retourne les codes de secours en clair. */
    private function enable(?User $user = null): array
    {
        $user ??= $this->user;
        $service = app(TwoFactor::class);
        $service->beginSetup($user);
        $codes = $service->confirm($user, $this->code($user));
        $this->assertNotNull($codes);
        $this->later(); // le code de confirmation est consommé : on passe au pas suivant

        return $codes;
    }

    private function loginUntilChallenge(): void
    {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    // --- Configuration depuis le profil ---

    public function test_setup_flow_secret_is_encrypted_and_enabled_only_after_valid_code(): void
    {
        $t = Livewire::actingAs($this->user)->test(Show::class)->call('startTwoFactor');
        $secret = $this->user->fresh()->two_factor_secret;
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $t->assertSee($secret)->assertSee('otpauth://totp/');
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());

        // Secret chiffré en base : la valeur brute n'est pas le secret.
        $raw = DB::table('users')->where('id', $this->user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, (string) $raw);

        $t->set('setupCode', '000000')->call('confirmTwoFactor')->assertHasErrors('setupCode');
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());

        $t->set('setupCode', $this->code())->call('confirmTwoFactor')->assertHasNoErrors();
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $t->get('recoveryCodes'));
        $t->assertSee('Codes de secours');
    }

    public function test_recovery_codes_are_stored_hashed_and_encrypted(): void
    {
        $plain = $this->enable();
        $raw = (string) DB::table('users')->where('id', $this->user->id)->value('two_factor_recovery_codes');
        foreach ($plain as $code) {
            $this->assertStringNotContainsString($code, $raw);
            $this->assertStringNotContainsString(str_replace('-', '', $code), $raw);
        }
        foreach ($this->user->fresh()->two_factor_recovery_codes as $stored) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored);
            $this->assertNotContains($stored, $plain);
        }
    }

    public function test_secret_is_hidden_in_serialization_and_not_displayed_once_enabled(): void
    {
        $this->enable();
        $this->assertArrayNotHasKey('two_factor_secret', $this->user->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $this->user->fresh()->toArray());
        Livewire::actingAs($this->user)->test(Show::class)->assertDontSee($this->user->fresh()->two_factor_secret)->assertSee('Activée');
    }

    public function test_cancel_setup_discards_unconfirmed_secret(): void
    {
        Livewire::actingAs($this->user)->test(Show::class)->call('startTwoFactor')->call('cancelTwoFactorSetup');
        $this->assertNull($this->user->fresh()->two_factor_secret);
    }

    public function test_cancel_setup_cannot_disable_an_active_2fa(): void
    {
        $this->enable();
        Livewire::actingAs($this->user)->test(Show::class)->call('cancelTwoFactorSetup');
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_start_setup_does_not_replace_the_secret_of_an_active_2fa(): void
    {
        $this->enable();
        $before = $this->user->fresh()->two_factor_secret;
        Livewire::actingAs($this->user)->test(Show::class)->call('startTwoFactor');
        $this->assertSame($before, $this->user->fresh()->two_factor_secret);
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
    }

    // --- Connexion ---

    public function test_login_without_2fa_is_unchanged(): void
    {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_login_with_2fa_requires_the_challenge(): void
    {
        $this->enable();
        $this->loginUntilChallenge();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        Livewire::test(TwoFactorChallenge::class)->set('code', $this->code())->call('verify')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_wrong_password_never_reaches_the_challenge(): void
    {
        $this->enable();
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'faux')->call('login')->assertHasErrors('email');
        $this->assertNull(session(TwoFactorChallenge::SESSION_KEY));
    }

    public function test_wrong_code_is_rejected_and_user_stays_guest(): void
    {
        $this->enable();
        $this->loginUntilChallenge();

        Livewire::test(TwoFactorChallenge::class)->set('code', '123456')->call('verify')->assertHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_without_pending_login_redirects_to_login(): void
    {
        Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_pending_login_expires(): void
    {
        $this->enable();
        $this->loginUntilChallenge();
        $this->later(config('security.totp.pending_login_minutes') * 60 + 5);

        Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_replayed_within_its_window(): void
    {
        $this->enable();
        $code = $this->code();

        $this->loginUntilChallenge();
        Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertRedirect(route('dashboard'));
        auth()->logout();

        // Même code, même fenêtre : refusé.
        $this->loginUntilChallenge();
        Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasErrors('code');
        $this->assertGuest();

        // Un code plus ancien (pas précédent) est aussi refusé une fois un pas plus récent consommé.
        $older = $this->code(null, -1);
        Livewire::test(TwoFactorChallenge::class)->set('code', $older)->call('verify')->assertHasErrors('code');
        $this->assertGuest();

        // Le code du pas suivant est accepté.
        $this->later();
        Livewire::test(TwoFactorChallenge::class)->set('code', $this->code())->call('verify')->assertRedirect(route('dashboard'));
    }

    public function test_tolerance_window_is_minimal(): void
    {
        $this->enable(); // dernier pas accepté : S0 ; le temps est maintenant S0 + 1
        $this->later(60); // S0 + 3

        $this->loginUntilChallenge();
        // 2 pas dans le passé : hors fenêtre (±1).
        Livewire::test(TwoFactorChallenge::class)->set('code', $this->code(null, -2))->call('verify')->assertHasErrors('code');
        // 2 pas dans le futur : hors fenêtre.
        Livewire::test(TwoFactorChallenge::class)->set('code', $this->code(null, 2))->call('verify')->assertHasErrors('code');
        $this->assertGuest();
        // 1 pas dans le passé : accepté.
        Livewire::test(TwoFactorChallenge::class)->set('code', $this->code(null, -1))->call('verify')->assertRedirect(route('dashboard'));
    }

    public function test_challenge_attempts_are_rate_limited_even_with_the_right_code(): void
    {
        config(['security.rate_limits.two_factor_challenge' => ['max' => 3, 'decay' => 300]]);
        $this->enable();
        $this->loginUntilChallenge();

        $t = Livewire::test(TwoFactorChallenge::class);
        for ($i = 0; $i < 3; $i++) {
            $t->set('code', '00000'.$i)->call('verify')->assertHasErrors('code');
        }
        $t->set('code', $this->code())->call('verify')->assertHasErrors('code');
        $this->assertGuest();

        // Après expiration de la limite, le bon code passe.
        $this->later(301);
        $t->set('code', $this->code())->call('verify')->assertRedirect(route('dashboard'));
    }

    public function test_recovery_code_is_single_use_and_normalized(): void
    {
        $codes = $this->enable();

        $this->loginUntilChallenge();
        Livewire::test(TwoFactorChallenge::class)->set('code', strtoupper($codes[0]))->call('verify')->assertRedirect(route('dashboard'));
        $this->assertCount(7, $this->user->fresh()->two_factor_recovery_codes);
        auth()->logout();

        $this->loginUntilChallenge();
        Livewire::test(TwoFactorChallenge::class)->set('code', $codes[0])->call('verify')->assertHasErrors('code');
        $this->assertGuest();
        // Un autre code de secours (sans tiret) fonctionne.
        Livewire::test(TwoFactorChallenge::class)->set('code', str_replace('-', '', $codes[1]))->call('verify')->assertRedirect(route('dashboard'));
        $this->assertCount(6, $this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_code_of_another_user_is_refused(): void
    {
        $this->enable();
        $other = User::factory()->create();
        $otherCodes = $this->enable($other);

        $this->loginUntilChallenge();
        Livewire::test(TwoFactorChallenge::class)->set('code', $otherCodes[0])->call('verify')->assertHasErrors('code');
        $this->assertGuest();
        $this->assertCount(8, $other->fresh()->two_factor_recovery_codes);
    }

    public function test_remember_choice_is_kept_through_the_challenge(): void
    {
        $this->enable();
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')->set('remember', true)->call('login');
        $this->assertTrue(session(TwoFactorChallenge::SESSION_KEY)['remember']);
    }

    // --- Désactivation et codes de secours ---

    public function test_disable_requires_password_and_code(): void
    {
        $this->enable();
        $t = Livewire::actingAs($this->user)->test(Show::class);

        $t->set('twoFactorPassword', 'mauvais')->set('twoFactorCode', $this->code())->call('disableTwoFactor')->assertHasErrors('twoFactorPassword');
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());

        $t->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', '000000')->call('disableTwoFactor')->assertHasErrors('twoFactorCode');
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());

        $t->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $this->code())->call('disableTwoFactor')->assertHasNoErrors();
        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_disable_accepts_a_recovery_code(): void
    {
        $codes = $this->enable();
        Livewire::actingAs($this->user)->test(Show::class)->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $codes[2])
            ->call('disableTwoFactor')->assertHasNoErrors();
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_after_disabling_login_no_longer_asks_for_a_code(): void
    {
        $this->enable();
        Livewire::actingAs($this->user)->test(Show::class)->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $this->code())->call('disableTwoFactor');
        auth()->logout();

        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_regenerating_recovery_codes_requires_password_and_code_and_invalidates_old_ones(): void
    {
        $old = $this->enable();
        $t = Livewire::actingAs($this->user)->test(Show::class);

        $t->set('twoFactorPassword', 'mauvais')->set('twoFactorCode', $this->code())->call('regenerateRecoveryCodes')->assertHasErrors('twoFactorPassword');
        $this->assertCount(0, $t->get('recoveryCodes'));

        $t->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $this->code())->call('regenerateRecoveryCodes')->assertHasNoErrors();
        $new = $t->get('recoveryCodes');
        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($old, $new));

        $service = app(TwoFactor::class);
        $this->assertFalse($service->verifyRecoveryCode($this->user->fresh(), $old[0]));
        $this->assertTrue($service->verifyRecoveryCode($this->user->fresh(), $new[0]));
    }

    public function test_sensitive_profile_actions_are_rate_limited(): void
    {
        config(['security.rate_limits.sensitive_profile' => ['max' => 2, 'decay' => 300]]);
        $this->enable();
        $t = Livewire::actingAs($this->user)->test(Show::class);

        for ($i = 0; $i < 2; $i++) {
            $t->set('twoFactorPassword', 'mauvais')->set('twoFactorCode', '000000')->call('disableTwoFactor')->assertHasErrors('twoFactorPassword');
        }
        // 3e tentative, même avec les bons éléments : bloquée.
        $t->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $this->code())->call('disableTwoFactor')->assertHasErrors('twoFactorCode');
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
    }

    // --- Service : cas limites ---

    public function test_confirm_is_impossible_without_a_pending_setup_or_when_already_active(): void
    {
        $service = app(TwoFactor::class);
        $this->assertNull($service->confirm($this->user, '123456'));
        $this->enable();
        $this->assertNull($service->confirm($this->user->fresh(), $this->code()));
        $this->assertFalse($service->verifyTotp(User::factory()->create(), '123456'));
    }

    public function test_confirmation_code_cannot_be_reused_for_login(): void
    {
        $service = app(TwoFactor::class);
        $service->beginSetup($this->user);
        $code = $this->code();
        $service->confirm($this->user, $code);

        $this->assertFalse($service->verifyTotp($this->user->fresh(), $code));
    }

    public function test_concurrent_acceptance_of_the_same_step_succeeds_only_once(): void
    {
        $this->enable();
        $code = $this->code();
        $service = app(TwoFactor::class);
        // Deux instances de l'utilisateur chargées avant l'un ou l'autre (deux requêtes simultanées).
        $a = User::find($this->user->id);
        $b = User::find($this->user->id);

        $this->assertTrue($service->verifyTotp($a, $code));
        $this->assertFalse($service->verifyTotp($b, $code));
    }

    // --- Obligation pour les administrateurs (D10/D40) ---

    public function test_not_required_by_default(): void
    {
        $this->assertFalse(config('security.require_2fa_for_admins'));
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
    }

    public function test_when_required_owner_without_2fa_is_sent_to_profile(): void
    {
        config(['security.require_2fa_for_admins' => true]);

        $this->actingAs($this->user)->get(route('dashboard'))->assertRedirect(route('profile'));
        $this->actingAs($this->user)->get(route('contacts.index'))->assertRedirect(route('profile'));
        $this->actingAs($this->user)->get(route('profile'))->assertOk();
        $this->actingAs($this->user)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_when_required_company_admin_and_platform_admin_are_concerned_but_not_members(): void
    {
        config(['security.require_2fa_for_admins' => true]);
        $company = Company::where('name', 'Acme')->first();

        $admin = User::factory()->create();
        $company->users()->attach($admin->id, ['role' => 'admin']);
        $member = User::factory()->create();
        $company->users()->attach($member->id, ['role' => 'member']);
        $platform = User::factory()->create();
        $platform->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('profile'));
        $this->actingAs($platform)->get(route('admin.companies'))->assertRedirect(route('profile'));
        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    public function test_when_required_user_with_2fa_passes_and_cannot_disable_it(): void
    {
        $this->enable();
        config(['security.require_2fa_for_admins' => true]);

        $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        Livewire::actingAs($this->user)->test(Show::class)->set('twoFactorPassword', 'mot-de-passe-solide-1')->set('twoFactorCode', $this->code())
            ->call('disableTwoFactor')->assertHasErrors('twoFactorCode');
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());
    }

    /** Page ouverte, puis requête de mise à jour Livewire brute (snapshot réel extrait du HTML). */
    private function livewireUpdateFromOpenPage(bool $required): TestResponse
    {
        $this->actingAs($this->user);
        $html = $this->get(route('contacts.index'))->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = html_entity_decode($m[1], ENT_QUOTES);
        $this->assertNotSame('', $snapshot);

        config(['security.require_2fa_for_admins' => $required]); // activée APRÈS l'ouverture de la page

        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ]);
    }

    public function test_livewire_update_is_accepted_when_2fa_is_not_required(): void
    {
        $this->livewireUpdateFromOpenPage(false)->assertOk();
    }

    public function test_requirement_also_blocks_livewire_updates_of_an_already_open_page(): void
    {
        $this->livewireUpdateFromOpenPage(true)->assertForbidden();
    }
}
