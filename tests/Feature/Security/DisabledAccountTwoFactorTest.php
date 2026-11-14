<?php

namespace Tests\Feature\Security;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\Company;
use App\Models\User;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Interaction entre l'étape 8 (comptes désactivés) et l'étape 9 (2FA) : un compte désactivé n'obtient jamais de session. */
class DisabledAccountTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:10'));
        $this->user = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($this->user, 'Acme');
        $service = app(TwoFactor::class);
        $service->beginSetup($this->user);
        $service->confirm($this->user, Totp::at($this->user->fresh()->two_factor_secret, now()->timestamp));
        Carbon::setTestNow(now()->addSeconds(30)); // le code de confirmation est consommé
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function disable(): void
    {
        $this->user->forceFill(['disabled_at' => now(), 'disabled_reason' => 'test'])->save();
    }

    public function test_disabled_account_with_2fa_cannot_even_reach_the_challenge(): void
    {
        $this->disable();

        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertHasErrors('email')->assertNoRedirect();

        $this->assertGuest();
        $this->assertNull(session(TwoFactorChallenge::SESSION_KEY)); // aucun défi en attente
    }

    public function test_account_disabled_between_password_and_code_gets_no_session(): void
    {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        // L'écran de défi est ouvert, puis l'administrateur désactive le compte pendant la fenêtre du défi.
        $challenge = Livewire::test(TwoFactorChallenge::class);
        $this->disable();

        $code = Totp::at($this->user->fresh()->two_factor_secret, now()->timestamp);
        $challenge->set('code', $code)->call('verify')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_challenge_page_itself_redirects_when_the_account_was_disabled(): void
    {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')->call('login');
        $this->disable();

        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_account_with_2fa_still_logs_in_normally(): void
    {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('two-factor.challenge'));

        $code = Totp::at($this->user->fresh()->two_factor_secret, now()->timestamp);
        Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->user);
    }
}
