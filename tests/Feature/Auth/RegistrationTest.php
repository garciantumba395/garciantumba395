<?php

namespace Tests\Feature\Auth;

use App\Enums\CompanyRole;
use App\Livewire\Auth\Register;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $o = []): array
    {
        return array_merge([
            'name' => 'Jean Mukendi', 'email' => 'jean@example.test', 'company_name' => 'Acme Kinshasa',
            'password' => 'mot-de-passe-solide-1', 'password_confirmation' => 'mot-de-passe-solide-1',
        ], $o);
    }

    public function test_page_is_displayed_in_french(): void
    {
        $this->get('/inscription')->assertOk()->assertSee('Créer mon compte');
    }

    public function test_registration_creates_user_company_and_owner_membership(): void
    {
        Livewire::test(Register::class)->set($this->valid())->call('register')
            ->assertHasNoErrors()->assertRedirect(route('verification.notice'));

        $user = User::firstWhere('email', 'jean@example.test');
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_platform_admin);

        $company = Company::firstWhere('name', 'Acme Kinshasa');
        $this->assertSame('acme-kinshasa', $company->slug);
        $this->assertSame(CompanyRole::Owner->value, $company->users()->first()->pivot->role);
    }

    public function test_slug_is_unique_for_same_company_name(): void
    {
        Livewire::test(Register::class)->set($this->valid())->call('register');
        auth()->logout();
        Livewire::test(Register::class)->set($this->valid(['email' => 'autre@example.test']))->call('register');

        $this->assertEqualsCanonicalizing(['acme-kinshasa', 'acme-kinshasa-2'], Company::pluck('slug')->all());
    }

    public function test_password_rules_and_duplicate_email_are_enforced(): void
    {
        User::factory()->create(['email' => 'jean@example.test']);

        Livewire::test(Register::class)->set($this->valid(['password' => 'court', 'password_confirmation' => 'court']))
            ->call('register')->assertHasErrors(['password', 'email']);

        $this->assertSame(0, Company::count());
    }

    public function test_cannot_mass_assign_platform_admin(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'abcdefghij', 'is_platform_admin' => true]);

        $this->assertFalse($user->fresh()->is_platform_admin);
    }
}
