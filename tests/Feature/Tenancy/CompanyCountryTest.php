<?php

namespace Tests\Feature\Tenancy;

use App\Livewire\Companies\Settings;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyCountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_company_defaults_to_drc(): void
    {
        $this->assertSame('CD', Company::createFor(User::factory()->create(), 'X')->fresh()->country);
    }

    public function test_admin_can_change_country_but_invalid_codes_are_rejected(): void
    {
        $u = User::factory()->create();
        $c = Company::createFor($u, 'X');

        Livewire::actingAs($u)->test(Settings::class)->set('country', 'ZZ')->call('save')->assertHasErrors('country');
        Livewire::actingAs($u)->test(Settings::class)->set('country', 'fr')->call('save')->assertHasNoErrors();

        $this->assertSame('FR', $c->fresh()->country);
    }
}
