<?php

namespace Tests\Feature\Contacts;

use App\Livewire\Contacts\Form;
use App\Livewire\Contacts\Index;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $ua;

    private User $ub;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->ua] = $this->tenant();
        [$this->b, $this->ub] = $this->tenant();
    }

    private function tenant(): array
    {
        $u = User::factory()->create();

        return [Company::createFor($u, 'T'.uniqid()), $u];
    }

    private function in(Company $c, callable $fn): mixed
    {
        $cur = app(CurrentCompany::class);
        $cur->set($c);
        try {
            return $fn();
        } finally {
            $cur->forget();
        }
    }

    public function test_create_contact_normalizes_phone_and_links_groups(): void
    {
        $g = $this->in($this->a, fn () => ContactGroup::factory()->create());

        Livewire::actingAs($this->ua)->test(Form::class)
            ->set('name', 'Marie')->set('phone', '0812345678')->set('groupIds', [$g->id])
            ->call('save')->assertHasNoErrors()->assertRedirect(route('contacts.index'));

        $c = $this->in($this->a, fn () => Contact::firstWhere('name', 'Marie'));
        $this->assertSame('+243812345678', $c->phone);
        $this->assertSame($this->a->id, $c->company_id);
        $this->assertSame(1, $this->in($this->a, fn () => $g->contacts()->count()));
    }

    public function test_invalid_phone_is_rejected(): void
    {
        Livewire::actingAs($this->ua)->test(Form::class)->set('phone', '123')->call('save')->assertHasErrors('phone');
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
    }

    public function test_duplicate_phone_rejected_within_company_but_allowed_across_companies(): void
    {
        $this->in($this->a, fn () => Contact::create(['phone' => '+243812345678']));

        Livewire::actingAs($this->ua)->test(Form::class)->set('phone', '081 234 5678')->call('save')->assertHasErrors('phone');
        Livewire::actingAs($this->ub)->test(Form::class)->set('phone', '081 234 5678')->call('save')->assertHasNoErrors();

        $this->assertSame(2, Contact::withoutGlobalScopes()->where('phone', '+243812345678')->count());
    }

    public function test_index_lists_only_own_contacts_and_search_does_not_leak(): void
    {
        $this->in($this->a, fn () => Contact::create(['name' => 'Alice A', 'phone' => '+243811111111']));
        $this->in($this->b, fn () => Contact::create(['name' => 'Bob B', 'phone' => '+243822222222']));

        Livewire::actingAs($this->ua)->test(Index::class)->assertSee('Alice A')->assertDontSee('Bob B')
            ->set('search', 'Bob')->assertDontSee('Bob B')->assertSee('Aucun contact');
    }

    public function test_cannot_open_edit_or_delete_another_companys_contact(): void
    {
        $foreign = $this->in($this->b, fn () => Contact::create(['name' => 'Secret', 'phone' => '+243833333333']));

        $this->actingAs($this->ua)->get(route('contacts.edit', $foreign->id))->assertNotFound();
        Livewire::actingAs($this->ua)->test(Index::class)->call('delete', $foreign->id)->assertNotFound();
        Livewire::actingAs($this->ua)->test(Form::class, ['contact' => $foreign->id])->assertNotFound();

        $this->assertNotNull($this->in($this->b, fn () => Contact::find($foreign->id)));
    }

    public function test_cannot_link_contact_to_another_companys_group_via_forged_ids(): void
    {
        $foreignGroup = $this->in($this->b, fn () => ContactGroup::factory()->create());

        Livewire::actingAs($this->ua)->test(Form::class)->set('phone', '0812345678')->set('groupIds', [$foreignGroup->id])->call('save');

        $this->assertSame(0, \DB::table('contact_group_contact')->count());
    }

    public function test_database_rejects_cross_company_pivot_even_if_app_logic_is_bypassed(): void
    {
        $contact = $this->in($this->a, fn () => Contact::create(['phone' => '+243844444444']));
        $group = $this->in($this->b, fn () => ContactGroup::factory()->create());

        $this->expectException(QueryException::class);
        \DB::table('contact_group_contact')->insert([
            'company_id' => $this->a->id, 'contact_id' => $contact->id, 'contact_group_id' => $group->id,
        ]);
    }

    public function test_deleting_a_group_keeps_contacts(): void
    {
        $this->in($this->a, function () {
            $g = ContactGroup::factory()->create();
            $c = Contact::create(['phone' => '+243855555555']);
            $g->addContacts([$c->id]);
            $g->delete();
            $this->assertNotNull(Contact::find($c->id));
        });
    }

    public function test_guest_cannot_access_contact_pages(): void
    {
        foreach (['/contacts', '/contacts/nouveau', '/contacts/importer', '/groupes'] as $u) {
            $this->get($u)->assertRedirect('/connexion');
        }
    }
}
