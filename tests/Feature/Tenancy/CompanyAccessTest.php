<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CompanyRole;
use App\Livewire\Companies\Create;
use App\Livewire\Companies\Members;
use App\Livewire\Companies\Settings;
use App\Livewire\Companies\Switcher;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function member(Company $c, CompanyRole $role): User
    {
        $u = User::factory()->create();
        $c->users()->attach($u, ['role' => $role->value]);

        return $u;
    }

    public function test_members_page_lists_only_current_company_members(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $ua = $this->member($a, CompanyRole::Owner);
        $ub = $this->member($b, CompanyRole::Owner);

        Livewire::actingAs($ua)->test(Members::class)->assertSee($ua->email)->assertDontSee($ub->email);
    }

    public function test_user_cannot_switch_to_company_they_do_not_belong_to(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $ua = $this->member($a, CompanyRole::Owner);

        Livewire::actingAs($ua)->test(Switcher::class)->call('switch', $b->id)->assertForbidden();
        $this->assertSame($a->id, app(CurrentCompany::class)->id());
    }

    public function test_user_in_two_companies_can_switch_and_context_follows(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $u = $this->member($a, CompanyRole::Owner);
        $b->users()->attach($u, ['role' => 'member']);

        Livewire::actingAs($u)->test(Switcher::class)->call('switch', $b->id)->assertRedirect(route('dashboard'));
        $this->assertSame($b->id, app(CurrentCompany::class)->id());
        $this->assertSame(CompanyRole::Member, app(CurrentCompany::class)->role());
    }

    public function test_inactive_company_is_not_selectable(): void
    {
        $a = Company::factory()->create(['is_active' => false]);
        $u = $this->member($a, CompanyRole::Owner);

        $this->actingAs($u)->get('/tableau-de-bord')->assertRedirect(route('companies.create'));
    }

    public function test_only_owner_or_admin_can_edit_company_settings(): void
    {
        $c = Company::factory()->create(['name' => 'Avant']);
        $owner = $this->member($c, CompanyRole::Owner);
        $admin = $this->member($c, CompanyRole::Admin);
        $plain = $this->member($c, CompanyRole::Member);

        Livewire::actingAs($plain)->test(Settings::class)->assertForbidden();
        Livewire::actingAs($admin)->test(Settings::class)->set('name', 'Après')->call('save')->assertHasNoErrors();
        $this->assertSame('Après', $c->fresh()->name);
        Livewire::actingAs($owner)->test(Settings::class)->assertOk();
    }

    public function test_settings_update_only_touches_the_active_company(): void
    {
        $a = Company::factory()->create(['name' => 'A']);
        $b = Company::factory()->create(['name' => 'B']);
        $ua = $this->member($a, CompanyRole::Admin);

        Livewire::actingAs($ua)->test(Settings::class)->set('name', 'A2')->call('save');

        $this->assertSame('A2', $a->fresh()->name);
        $this->assertSame('B', $b->fresh()->name);
    }

    public function test_platform_admin_flag_grants_no_access_to_company_data(): void
    {
        $c = Company::factory()->create();
        $this->member($c, CompanyRole::Owner);
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)->get('/entreprise/membres')->assertRedirect(route('companies.create'));
    }

    public function test_user_can_create_additional_company_and_becomes_owner(): void
    {
        $a = Company::factory()->create();
        $u = $this->member($a, CompanyRole::Member);

        Livewire::actingAs($u)->test(Create::class)->set('name', 'Seconde SARL')->call('save')->assertRedirect(route('dashboard'));

        $second = Company::firstWhere('name', 'Seconde SARL');
        $this->assertSame('owner', $second->users()->find($u->id)->pivot->role);
        $this->assertSame($second->id, app(CurrentCompany::class)->id());
    }
}
