<?php

namespace Tests\Feature\Contacts;

use App\Livewire\Groups\Index;
use App\Models\Company;
use App\Models\ContactGroup;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $u = User::factory()->create();

        return [Company::createFor($u, 'G'.uniqid()), $u];
    }

    public function test_group_names_unique_per_company_only(): void
    {
        [, $ua] = $this->tenant();
        [, $ub] = $this->tenant();

        Livewire::actingAs($ua)->test(Index::class)->set('name', 'VIP')->call('create')->assertHasNoErrors()
            ->set('name', 'VIP')->call('create')->assertHasErrors('name');
        Livewire::actingAs($ub)->test(Index::class)->set('name', 'VIP')->call('create')->assertHasNoErrors();
    }

    public function test_user_sees_and_deletes_only_own_groups(): void
    {
        [$a, $ua] = $this->tenant();
        [$b] = $this->tenant();
        app(CurrentCompany::class)->set($b);
        $foreign = ContactGroup::create(['name' => 'Autre']);
        app(CurrentCompany::class)->forget();

        Livewire::actingAs($ua)->test(Index::class)->assertDontSee('Autre')->call('delete', $foreign->id)->assertNotFound();
        $this->assertTrue(ContactGroup::withoutGlobalScopes()->whereKey($foreign->id)->exists());
    }
}
