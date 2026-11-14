<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\AccountService;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class UserDisableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $other;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->other = User::factory()->create();
        $this->other->forceFill(['is_platform_admin' => true])->save();
        $this->victim = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($this->victim, 'Acme');
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        Livewire::actingAs($this->admin)->test(Users::class)
            ->set("reasons.{$this->victim->id}", 'Compte compromis')->call('disable', $this->victim->id)->assertHasNoErrors();
        Auth::logout();

        Livewire::test(Login::class)->set('email', $this->victim->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_reenabled_user_can_log_in_again(): void
    {
        app(AccountService::class)->disableUser($this->victim, $this->admin, 'Test');
        app(AccountService::class)->enableUser($this->victim->fresh(), 'Résolu');

        Livewire::test(Login::class)->set('email', $this->victim->email)->set('password', 'mot-de-passe-solide-1')
            ->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->victim);
    }

    public function test_current_session_of_a_disabled_user_is_invalidated_on_next_request(): void
    {
        $sessionKey = Auth::guard('web')->getName();
        $this->withSession([$sessionKey => $this->victim->id])->get('/tableau-de-bord')->assertOk();

        app(AccountService::class)->disableUser($this->victim, $this->admin, 'Compte compromis');
        Auth::forgetGuards(); // chaque vraie requête recharge l'utilisateur depuis la base

        $this->withSession([$sessionKey => $this->victim->id])->get('/tableau-de-bord')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_disabled_user_is_logged_out_on_livewire_update_requests_too(): void
    {
        $sessionKey = Auth::guard('web')->getName();
        app(AccountService::class)->disableUser($this->victim, $this->admin, 'Test');
        Auth::forgetGuards();

        // Les requêtes Livewire passent par le groupe « web » : même contrôle.
        $this->withSession([$sessionKey => $this->victim->id])->post(Livewire::getUpdateUri(), ['components' => []])->assertRedirect('/connexion');
    }

    public function test_database_sessions_of_the_user_are_deleted(): void
    {
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->victim->id, 'payload' => 'x', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $this->other->id, 'payload' => 'x', 'last_activity' => time()]);

        app(AccountService::class)->disableUser($this->victim, $this->admin, 'Test');

        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_disabled_column_is_not_mass_assignable(): void
    {
        $u = new User;
        $u->fill(['name' => 'x', 'disabled_at' => now(), 'is_platform_admin' => true]);
        $this->assertNull($u->disabled_at);
        $this->assertFalse((bool) $u->is_platform_admin);
    }

    public function test_cannot_disable_yourself(): void
    {
        Livewire::actingAs($this->admin)->test(Users::class)
            ->set("reasons.{$this->admin->id}", 'Test')->call('disable', $this->admin->id)->assertHasErrors("reasons.{$this->admin->id}");
        $this->assertNull($this->admin->fresh()->disabled_at);
        $this->assertSame(0, AuditLog::where('action', 'user.disabled')->count());
    }

    public function test_cannot_disable_yourself_even_as_the_service_is_called_directly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AccountService::class)->disableUser($this->admin, $this->admin, 'Test');
    }

    public function test_cannot_disable_the_last_active_platform_admin(): void
    {
        $service = app(AccountService::class);
        $service->disableUser($this->other, $this->admin, 'Départ'); // 2 admins actifs : autorisé ; il en reste un

        try {
            $service->disableUser($this->admin, $this->victim, 'Test'); // le dernier : refusé (acteur différent)
            $this->fail('La désactivation du dernier administrateur aurait dû être refusée.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('dernier administrateur', $e->getMessage());
        }
        $this->assertNull($this->admin->fresh()->disabled_at);
    }

    public function test_reason_is_required_to_disable_or_enable(): void
    {
        Livewire::actingAs($this->admin)->test(Users::class)->call('disable', $this->victim->id)->assertHasErrors("reasons.{$this->victim->id}");
        $this->assertNull($this->victim->fresh()->disabled_at);

        app(AccountService::class)->disableUser($this->victim, $this->admin, 'Test');
        Livewire::actingAs($this->admin)->test(Users::class)->call('enable', $this->victim->id)->assertHasErrors("reasons.{$this->victim->id}");
        $this->assertNotNull($this->victim->fresh()->disabled_at);
    }

    public function test_disabled_platform_admin_loses_admin_access(): void
    {
        $sessionKey = Auth::guard('web')->getName();
        app(AccountService::class)->disableUser($this->other, $this->admin, 'Test');
        Auth::forgetGuards();

        $this->withSession([$sessionKey => $this->other->id])->get('/admin')->assertRedirect('/connexion');
    }

    public function test_users_list_search(): void
    {
        $this->victim->forceFill(['name' => 'Zoé Introuvable'])->save();
        Livewire::actingAs($this->admin)->test(Users::class)->set('search', 'introuvable')->assertSee('Zoé Introuvable')->assertDontSee($this->other->email);
    }
}
