<?php

namespace Tests\Feature\Legal;

use App\Enums\CompanyRole;
use App\Livewire\Companies\LegalIdentity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LegalIdentityPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $co;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Acme');
    }

    private function fill($page, array $o = [])
    {
        $v = $o + ['legalName' => 'Acme SARL', 'legalAddress' => '5 avenue de la Paix, Kinshasa', 'legalEmail' => 'contact@acme.example.test'];
        foreach ($v as $k => $val) {
            $page->set($k, $val);
        }

        return $page;
    }

    public function test_owner_saves_the_identity_and_sees_that_real_sending_is_allowed(): void
    {
        $page = Livewire::actingAs($this->owner)->test(LegalIdentity::class)->assertSee('Incomplète');

        $this->fill($page, ['legalRccm' => 'CD/KIN/RCCM/1', 'legalPhone' => '+243810000000'])->call('save')->assertHasNoErrors()->assertSee('Identité complète');

        $c = $this->co->fresh();
        $this->assertTrue($c->hasCompleteLegalIdentity());
        $this->assertSame(['Acme SARL', 'CD/KIN/RCCM/1', null, null], [$c->legal_name, $c->legal_rccm, $c->legal_tax_id, $c->legal_act]);
    }

    public function test_name_address_and_email_are_mandatory_and_the_email_is_validated(): void
    {
        $page = Livewire::actingAs($this->owner)->test(LegalIdentity::class);

        $page->set('legalName', '')->set('legalAddress', '')->set('legalEmail', '')->call('save')->assertHasErrors(['legalName', 'legalAddress', 'legalEmail']);
        $this->fill($page, ['legalEmail' => 'pas-un-email'])->call('save')->assertHasErrors('legalEmail');
        $this->assertFalse($this->co->fresh()->hasCompleteLegalIdentity());
    }

    public function test_only_owners_and_admins_can_open_or_save_it(): void
    {
        $member = User::factory()->create();
        $this->co->users()->attach($member, ['role' => CompanyRole::Member->value]);

        Livewire::actingAs($member)->test(LegalIdentity::class)->assertForbidden();
        $this->actingAs($member)->get(route('companies.legal'))->assertForbidden();
        $this->assertFalse($this->co->fresh()->hasCompleteLegalIdentity());
    }

    public function test_guests_are_redirected_and_the_page_is_linked_from_the_settings(): void
    {
        $this->get('/entreprise/identite-legale')->assertRedirect('/connexion');

        $this->actingAs($this->owner)->get('/entreprise/parametres')->assertSee(route('companies.legal'), false)->assertSee('Identité légale');
    }

    public function test_it_only_changes_the_active_company_and_the_audit_holds_no_value(): void
    {
        $other = Company::createFor(User::factory()->create(), 'Autre');

        $this->fill(Livewire::actingAs($this->owner)->test(LegalIdentity::class))->call('save');

        $this->assertNull($other->fresh()->legal_name);
        $log = AuditLog::where('action', 'company.legal_identity_updated')->firstOrFail();
        $this->assertSame(['complete' => true], $log->details);
        $this->assertStringNotContainsString('acme.example.test', json_encode($log->details));
    }

    public function test_form_is_prefilled_with_the_saved_identity(): void
    {
        $this->co->update(['legal_name' => 'Acme SARL', 'legal_address' => 'Adresse', 'legal_email' => 'e@acme.example.test']);

        Livewire::actingAs($this->owner)->test(LegalIdentity::class)->assertSet('legalName', 'Acme SARL')->assertSet('legalEmail', 'e@acme.example.test');
    }
}
