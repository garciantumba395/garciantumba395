<?php

namespace Tests\Feature\Admin;

use App\Enums\CompanyRole;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\Companies;
use App\Livewire\Admin\CompanyShow;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Rates;
use App\Livewire\Admin\Users;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Autorisation de CHAQUE route et action d'administration de la plateforme. */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $member;

    private Company $co;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Cliente SARL');
        $this->co->users()->attach($this->member, ['role' => CompanyRole::Member->value]);
    }

    /** @return array<string,string> */
    private function routes(): array
    {
        return [
            'dashboard' => '/admin',
            'entreprises' => '/admin/entreprises',
            'fiche' => '/admin/entreprises/'.$this->co->id,
            'tarifs' => '/admin/tarifs',
            'utilisateurs' => '/admin/utilisateurs',
            'audit' => '/admin/audit',
        ];
    }

    public function test_guests_are_redirected_to_login_on_every_admin_route(): void
    {
        foreach ($this->routes() as $name => $url) {
            $this->get($url)->assertRedirect('/connexion', "route $name");
        }
    }

    public function test_company_owner_and_member_get_403_on_every_admin_route(): void
    {
        foreach ([$this->owner, $this->member] as $user) {
            foreach ($this->routes() as $name => $url) {
                $this->actingAs($user)->get($url)->assertForbidden("route $name");
            }
        }
    }

    public function test_platform_admin_reaches_every_admin_route(): void
    {
        foreach ($this->routes() as $name => $url) {
            $this->actingAs($this->admin)->get($url)->assertOk("route $name");
        }
    }

    public function test_every_livewire_component_refuses_non_admins_at_mount(): void
    {
        foreach ([$this->owner, $this->member] as $user) {
            Livewire::actingAs($user)->test(Dashboard::class)->assertForbidden();
            Livewire::actingAs($user)->test(Companies::class)->assertForbidden();
            Livewire::actingAs($user)->test(CompanyShow::class, ['company' => $this->co->id])->assertForbidden();
            Livewire::actingAs($user)->test(Users::class)->assertForbidden();
            Livewire::actingAs($user)->test(AuditLogs::class)->assertForbidden();
            Livewire::actingAs($user)->test(Rates::class)->assertForbidden();
        }
    }

    private function loseAdminThen(string $component, array $params, array $props, string $method, array $args = []): void
    {
        // Monte et remplit le composant en tant qu'admin, retire le statut, puis appelle l'action (revérification à CHAQUE action).
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $page = Livewire::actingAs($this->admin)->test($component, $params);
        foreach ($props as $k => $v) {
            $page->set($k, $v);
        }
        $this->admin->forceFill(['is_platform_admin' => false])->save();
        $page->call($method, ...$args)->assertForbidden();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_actions_recheck_admin_status_even_if_it_is_lost_after_mount(): void
    {
        $this->loseAdminThen(CompanyShow::class, ['company' => $this->co->id], ['reason' => 'Test'], 'disable');
        $this->loseAdminThen(CompanyShow::class, ['company' => $this->co->id], ['reason' => 'Test'], 'enable');
        $this->loseAdminThen(Users::class, [], ["reasons.{$this->owner->id}" => 'x'], 'disable', [$this->owner->id]);
        $this->loseAdminThen(Users::class, [], ["reasons.{$this->owner->id}" => 'x'], 'enable', [$this->owner->id]);
        $this->loseAdminThen(Companies::class, [], ['companyId' => $this->co->id, 'amount' => '10', 'reason' => 'x'], 'submit');
        $this->loseAdminThen(Companies::class, [], ['companyId' => $this->co->id, 'sendingReason' => 'x'], 'updateSending');
        $this->loseAdminThen(Rates::class, [], ['smsPer100' => '120'], 'add');
        // Rendu (rafraîchissement) : revérifié aussi.
        $this->loseAdminThen(Dashboard::class, [], [], '$refresh');
        $this->loseAdminThen(AuditLogs::class, [], [], '$refresh');

        $this->assertTrue($this->co->fresh()->is_active);
        $this->assertNull($this->owner->fresh()->disabled_at);
        $this->assertSame(0, (int) $this->co->fresh()->credit_balance_units);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_platform_admin_middleware_alone_refuses_non_admins(): void
    {
        // Couche « middleware » testée isolément (les composants ont leur propre contrôle).
        $middleware = new EnsurePlatformAdmin;
        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $this->owner);
        try {
            $middleware->handle($request, fn () => response('ok'));
            $this->fail('403 attendu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $request->setUserResolver(fn () => $this->admin);
        $this->assertSame('ok', $middleware->handle($request, fn () => response('ok'))->getContent());
        $request->setUserResolver(fn () => null);
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => response('ok'));
    }

    public function test_non_admin_cannot_perform_admin_actions_directly(): void
    {
        Livewire::actingAs($this->owner)->test(Users::class)->assertForbidden();
        $this->assertNull($this->member->fresh()->disabled_at);
        $this->assertTrue($this->co->fresh()->is_active);
    }

    public function test_platform_admin_status_gives_no_access_to_company_pages(): void
    {
        // Un administrateur de plateforme qui n'est membre d'aucune entreprise n'accède à aucune page d'entreprise.
        foreach (['/tableau-de-bord', '/contacts', '/groupes', '/desinscriptions', '/messages', '/campagnes', '/credits', '/entreprise/parametres', '/entreprise/membres'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertRedirect('/entreprises/nouvelle');
        }
    }

    public function test_admin_cannot_switch_into_a_company_he_does_not_belong_to(): void
    {
        $this->actingAs($this->admin)->withSession(['current_company_id' => $this->co->id])->get('/contacts')->assertRedirect('/entreprises/nouvelle');
    }

    public function test_company_pages_are_unaffected_for_members(): void
    {
        $this->actingAs($this->owner)->get('/contacts')->assertOk();
    }

    public function test_unknown_company_in_admin_sheet_is_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/entreprises/999999')->assertNotFound();
    }
}
