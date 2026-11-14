<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCompany(): User
    {
        $user = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($user, 'Acme');

        return $user;
    }

    public function test_login_succeeds(): void
    {
        $user = $this->userWithCompany();

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->userWithCompany();

        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'faux')
            ->call('login')->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = $this->userWithCompany();
        $c = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'faux');
        for ($i = 0; $i < 5; $i++) {
            $c->call('login');
        }
        // Même avec le bon mot de passe : bloqué.
        $c->set('password', 'mot-de-passe-solide-1')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        foreach (['/tableau-de-bord', '/entreprise/membres', '/entreprise/parametres', '/entreprises/nouvelle'] as $url) {
            $this->get($url)->assertRedirect('/connexion');
        }
    }

    public function test_authenticated_user_sees_dashboard_and_can_logout(): void
    {
        $user = $this->userWithCompany();

        $this->actingAs($user)->get('/tableau-de-bord')->assertOk()->assertSee('Acme')->assertSee('Propriétaire');
        $this->post('/deconnexion')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_user_without_company_is_sent_to_company_creation(): void
    {
        $this->actingAs(User::factory()->create())->get('/tableau-de-bord')->assertRedirect(route('companies.create'));
    }
}
