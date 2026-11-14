<?php

namespace Tests\Feature\Auth;

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

/** Page d'accueil après connexion et navigation selon le profil (l'opérateur n'a normalement aucune entreprise). */
class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false, bool $company = false): User
    {
        $u = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        if ($admin) {
            $u->forceFill(['is_platform_admin' => true])->save();
        }
        if ($company) {
            Company::createFor($u, 'Acme');
        }

        return $u;
    }

    private function login(User $u)
    {
        return Livewire::test(Login::class)->set('email', $u->email)->set('password', 'mot-de-passe-solide-1')->call('login');
    }

    public function test_home_route_depends_on_profile(): void
    {
        $this->assertSame('admin.dashboard', $this->user(admin: true)->homeRoute());
        $this->assertSame('dashboard', $this->user(admin: true, company: true)->homeRoute());
        $this->assertSame('dashboard', $this->user(company: true)->homeRoute());
        $this->assertSame('dashboard', $this->user()->homeRoute());
    }

    public function test_company_less_platform_admin_lands_in_administration_after_login(): void
    {
        $this->login($this->user(admin: true))->assertRedirect(route('admin.dashboard'));
    }

    public function test_other_profiles_still_land_on_the_dashboard(): void
    {
        $this->login($this->user(admin: true, company: true))->assertRedirect(route('dashboard'));
        $this->login($this->user(company: true))->assertRedirect(route('dashboard'));
    }

    public function test_admin_landing_also_applies_after_the_2fa_challenge(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:10'));
        $admin = $this->user(admin: true);
        $service = app(TwoFactor::class);
        $service->beginSetup($admin);
        $service->confirm($admin, Totp::at($admin->fresh()->two_factor_secret, now()->timestamp));
        Carbon::setTestNow(now()->addSeconds(30));

        $this->login($admin)->assertRedirect(route('two-factor.challenge'));
        Livewire::test(TwoFactorChallenge::class)->set('code', Totp::at($admin->fresh()->two_factor_secret, now()->timestamp))
            ->call('verify')->assertRedirect(route('admin.dashboard'));
        Carbon::setTestNow();
    }

    public function test_signed_in_company_less_admin_is_sent_to_administration_from_the_login_page(): void
    {
        $this->actingAs($this->user(admin: true))->get('/connexion')->assertRedirect(route('admin.dashboard'));
    }

    public function test_signed_in_company_member_is_sent_to_the_dashboard_from_the_login_page(): void
    {
        $this->actingAs($this->user(company: true))->get('/connexion')->assertRedirect(route('dashboard'));
    }

    public function test_landing_in_administration_grants_no_access_to_company_pages(): void
    {
        $this->actingAs($this->user(admin: true))->get('/contacts')->assertRedirect(route('companies.create'));
    }

    public function test_navigation_is_complete_for_a_user_with_a_company(): void
    {
        $this->actingAs($this->user(company: true))->get('/tableau-de-bord')
            ->assertSee('>Contacts<', false)->assertSee('>Rapports<', false)->assertSee('>Membres<', false)->assertDontSee('Administration');
    }

    public function test_navigation_of_a_company_less_admin_has_no_company_links(): void
    {
        $this->actingAs($this->user(admin: true))->get('/admin')->assertSee('Administration')->assertSee('>Profil<', false)
            ->assertDontSee('>Contacts<', false)->assertDontSee('>Campagnes<', false)->assertDontSee('>Membres<', false);
    }

    public function test_company_creation_page_shows_no_unusable_links(): void
    {
        $this->actingAs($this->user())->get('/entreprises/nouvelle')->assertSee('Nouvelle entreprise')
            ->assertDontSee('>Contacts<', false)->assertDontSee('>Crédits<', false)->assertSee('>Profil<', false);
    }
}
