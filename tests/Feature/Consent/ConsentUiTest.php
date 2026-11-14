<?php

namespace Tests\Feature\Consent;

use App\Domain\Consent\ConsentService;
use App\Enums\CompanyRole;
use App\Livewire\Contacts\Import;
use App\Livewire\Contacts\Show;
use App\Livewire\Suppressions\Index;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Suppression;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ConsentUiTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $ua;

    private User $ub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ua = User::factory()->create();
        $this->ub = User::factory()->create();
        $this->a = Company::createFor($this->ua, 'A');
        $this->b = Company::createFor($this->ub, 'B');
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

    private function contact(Company $c, string $phone = '+243812345678'): Contact
    {
        return $this->in($c, fn () => Contact::create(['phone' => $phone]));
    }

    public function test_record_consent_from_contact_page(): void
    {
        $c = $this->contact($this->a);

        Livewire::actingAs($this->ua)->test(Show::class, ['contact' => $c->id])
            ->assertSee('Sans consentement')
            ->set('source', 'paper')->set('evidence', 'Formulaire n°12')->set('obtainedAt', now()->subDay()->toDateString())
            ->call('recordConsent')->assertHasNoErrors()->assertSee('Consentement valide');

        $k = $this->in($this->a, fn () => Consent::first());
        $this->assertSame($this->ua->id, $k->recorded_by);
        $this->assertSame('Formulaire n°12', $k->evidence);
    }

    public function test_consent_form_requires_evidence_and_past_date(): void
    {
        $c = $this->contact($this->a);

        Livewire::actingAs($this->ua)->test(Show::class, ['contact' => $c->id])
            ->set('evidence', '')->set('obtainedAt', now()->addDays(2)->toDateString())
            ->call('recordConsent')->assertHasErrors(['evidence', 'obtainedAt']);
        $this->assertSame(0, Consent::withoutGlobalScopes()->count());
    }

    public function test_opt_out_from_contact_page_blocks_everything(): void
    {
        $c = $this->contact($this->a);

        Livewire::actingAs($this->ua)->test(Show::class, ['contact' => $c->id])
            ->call('optOut')->assertSee('Désinscrit')->assertDontSee('Enregistrer le consentement');

        $this->assertSame(1, Suppression::withoutGlobalScopes()->count());
    }

    public function test_cannot_view_or_act_on_another_companys_contact_or_consent(): void
    {
        $foreign = $this->contact($this->b);
        $k = $this->in($this->b, fn () => app(ConsentService::class)->record($foreign, 'paper', 'x', now()->subDay()));

        $this->actingAs($this->ua)->get(route('contacts.show', $foreign->id))->assertNotFound();
        Livewire::actingAs($this->ua)->test(Show::class, ['contact' => $foreign->id])->assertNotFound();

        $mine = $this->contact($this->a, '+243822222222');
        // Révoquer un consentement d'une autre entreprise via sa propre fiche est impossible.
        Livewire::actingAs($this->ua)->test(Show::class, ['contact' => $mine->id])->call('revoke', $k->id)->assertNotFound();
        $this->assertNull($this->in($this->b, fn () => Consent::find($k->id))->revoked_at);
    }

    public function test_suppression_page_adds_numbers_and_ignores_invalid(): void
    {
        $c = $this->contact($this->a);

        Livewire::actingAs($this->ua)->test(Index::class)
            ->set('numbers', "0812345678\nabc, 0971234567")->set('reason', 'Demande par appel')->call('add')
            ->assertSee('+243812345678')->assertSee('+243971234567');

        $this->assertSame(2, $this->in($this->a, fn () => Suppression::count()));
        $this->assertSame(0, $this->in($this->b, fn () => Suppression::count()));
        $this->assertNotNull($c);
    }

    public function test_only_admins_can_lift_a_suppression_and_a_reason_is_required(): void
    {
        $member = User::factory()->create();
        $this->a->users()->attach($member, ['role' => CompanyRole::Member->value]);
        $s = $this->in($this->a, fn () => app(ConsentService::class)->optOut('+243812345678'));

        Livewire::actingAs($member)->test(Index::class)->call('lift', $s->id)->assertForbidden();

        $page = Livewire::actingAs($this->ua)->test(Index::class)->call('lift', $s->id)->assertHasErrors(["liftReasons.{$s->id}"]);
        $page->set("liftReasons.{$s->id}", 'Nouvelle demande écrite')->call('lift', $s->id)->assertHasNoErrors();

        $this->assertSame(0, $this->in($this->a, fn () => Suppression::active()->count()));
        $this->assertSame('Nouvelle demande écrite', Suppression::withoutGlobalScopes()->first()->lift_reason); // historique conservé
    }

    public function test_cannot_lift_another_companys_suppression(): void
    {
        $s = $this->in($this->b, fn () => app(ConsentService::class)->optOut('+243812345678'));

        Livewire::actingAs($this->ua)->test(Index::class)->set("liftReasons.{$s->id}", 'x')->call('lift', $s->id)->assertNotFound();
        $this->assertNull(Suppression::withoutGlobalScopes()->find($s->id)->lifted_at);
    }

    public function test_import_without_declaration_creates_no_consent(): void
    {
        $file = UploadedFile::fake()->createWithContent('c.csv', "telephone\n0812345678\n");
        Livewire::actingAs($this->ua)->test(Import::class)->set('file', $file)->call('import')->assertHasNoErrors();

        $this->in($this->a, function () {
            $this->assertSame(1, Contact::count());
            $this->assertSame(0, Consent::count());
            $this->assertSame(0, Contact::sendable()->count());
        });
    }

    public function test_import_with_declaration_records_consent_but_skips_suppressed_numbers(): void
    {
        $this->in($this->a, fn () => app(ConsentService::class)->optOut('+243971234567'));
        $file = UploadedFile::fake()->createWithContent('c.csv', "telephone\n0812345678\n0971234567\n");

        Livewire::actingAs($this->ua)->test(Import::class)->set('file', $file)
            ->set('recordConsent', true)->set('consentSource', 'web_form')->set('consentEvidence', 'Formulaire inscription v3')
            ->set('consentDate', now()->subDays(3)->toDateString())->call('import')->assertHasNoErrors();

        $this->in($this->a, function () {
            $imp = ContactImport::first();
            $this->assertSame(1, $imp->consent_count);
            $this->assertSame(1, $imp->suppressed_count);
            $this->assertSame(['+243812345678'], Contact::sendable()->pluck('phone')->all());
        });

        // Réimport : pas de consentement en double.
        $again = UploadedFile::fake()->createWithContent('c.csv', "telephone\n0812345678\n");
        Livewire::actingAs($this->ua)->test(Import::class)->set('file', $again)
            ->set('recordConsent', true)->set('consentSource', 'web_form')->set('consentEvidence', 'Formulaire inscription v3')
            ->set('consentDate', now()->subDays(3)->toDateString())->call('import');
        $this->assertSame(1, $this->in($this->a, fn () => Consent::count()));
    }

    public function test_import_declaration_requires_evidence_and_valid_date(): void
    {
        $file = UploadedFile::fake()->createWithContent('c.csv', "telephone\n0812345678\n");

        Livewire::actingAs($this->ua)->test(Import::class)->set('file', $file)
            ->set('recordConsent', true)->set('consentEvidence', '')->set('consentDate', now()->addDay()->toDateString())
            ->call('import')->assertHasErrors(['consentEvidence', 'consentDate']);
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());
    }

    public function test_contact_list_shows_statuses(): void
    {
        $ok = $this->contact($this->a, '+243811111111');
        $this->in($this->a, fn () => app(ConsentService::class)->record($ok, 'paper', 'x', now()->subDay()));
        $this->contact($this->a, '+243822222222');
        $this->in($this->a, fn () => app(ConsentService::class)->optOut('+243833333333'));
        $this->contact($this->a, '+243833333333');

        Livewire::actingAs($this->ua)->test(\App\Livewire\Contacts\Index::class)
            ->assertSee('Consentement')->assertSee('Sans consentement')->assertSee('Désinscrit')->assertSee('dont 1 avec consentement valide');
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/desinscriptions')->assertRedirect('/connexion');
    }
}
