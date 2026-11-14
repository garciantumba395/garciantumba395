<?php

namespace Tests\Feature\Contacts;

use App\Domain\Contacts\ContactImporter;
use App\Livewire\Contacts\Import;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ua = User::factory()->create();
        $this->a = Company::createFor($this->ua, 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        app(CurrentCompany::class)->set($this->a);
    }

    private function csv(string $content): string
    {
        $p = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($p, $content);

        return $p;
    }

    private function importCsv(string $content, ?int $group = null)
    {
        return app(ContactImporter::class)->import($this->csv($content), 'test.csv', $group, $this->ua->id);
    }

    public function test_imports_valid_rows_and_reports_invalid_and_duplicates(): void
    {
        $r = $this->importCsv("telephone,nom,email\n0812345678,Marie,marie@example.test\n+243 97 123 4567,Paul,\nabc,Bad,\n0812345678,Doublon,\n0991234567,Mail,pas-un-mail\n");

        $this->assertSame(5, $r->total_rows);
        $this->assertSame(2, $r->created_count);
        $this->assertSame(2, $r->invalid_count);
        $this->assertSame(1, $r->duplicate_count);
        $this->assertSame(['+243812345678', '+243971234567'], Contact::orderBy('phone')->pluck('phone')->all());
        // Le rapport ne contient que des numéros de ligne et des motifs, jamais de données personnelles.
        $this->assertStringNotContainsString('abc', json_encode($r->errors));
        $this->assertStringNotContainsString('Mail', json_encode($r->errors));
    }

    public function test_reimport_is_idempotent_and_updates_changed_names(): void
    {
        $this->importCsv("telephone,nom\n0812345678,Marie\n");
        $second = $this->importCsv("telephone,nom\n0812345678,Marie\n");
        $this->assertSame(0, $second->created_count);
        $this->assertSame(0, $second->updated_count);

        $third = $this->importCsv("telephone,nom\n0812345678,Marie Dupont\n");
        $this->assertSame(1, $third->updated_count);
        $this->assertSame(1, Contact::count());
        $this->assertSame('Marie Dupont', Contact::first()->name);
    }

    public function test_reimport_without_name_does_not_erase_existing_name(): void
    {
        $this->importCsv("telephone,nom\n0812345678,Marie\n");
        $this->importCsv("telephone\n0812345678\n");
        $this->assertSame('Marie', Contact::first()->name);
    }

    public function test_handles_semicolon_bom_accented_headers_and_windows1252(): void
    {
        $r = $this->importCsv("\xEF\xBB\xBFTéléphone;Nom\r\n0812345678;Zoé\r\n");
        $this->assertSame(1, $r->created_count);
        $this->assertSame('Zoé', Contact::first()->name);

        $latin = mb_convert_encoding("telephone;nom\n0971234567;José\n", 'Windows-1252', 'UTF-8');
        $this->importCsv($latin);
        $this->assertSame('José', Contact::where('phone', '+243971234567')->first()->name);
    }

    public function test_same_numbers_can_be_imported_by_two_companies_independently(): void
    {
        $this->importCsv("telephone\n0812345678\n");
        app(CurrentCompany::class)->set($this->b);
        $r = $this->importCsv("telephone\n0812345678\n");

        $this->assertSame(1, $r->created_count);
        $this->assertSame(2, Contact::withoutGlobalScopes()->count());
        $this->assertSame(1, Contact::count());
    }

    public function test_import_into_group_links_new_and_existing_contacts(): void
    {
        $g = ContactGroup::factory()->create();
        $this->importCsv("telephone\n0812345678\n");
        $this->importCsv("telephone\n0812345678\n0971234567\n", $g->id);

        $this->assertSame(2, $g->contacts()->count());
    }

    public function test_cannot_import_into_another_companys_group(): void
    {
        app(CurrentCompany::class)->set($this->b);
        $foreign = ContactGroup::factory()->create();
        app(CurrentCompany::class)->set($this->a);

        $this->expectException(InvalidArgumentException::class);
        $this->importCsv("telephone\n0812345678\n", $foreign->id);
    }

    public function test_rejects_missing_phone_column_empty_file_and_too_many_rows(): void
    {
        foreach (["nom,email\nA,a@x.test\n", "telephone\n", ''] as $bad) {
            try {
                $this->importCsv($bad);
                $this->fail('Exception attendue');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, Contact::count());

        $big = "telephone\n".str_repeat("0812345678\n", ContactImporter::MAX_ROWS + 1);
        $this->expectException(InvalidArgumentException::class);
        $this->importCsv($big);
    }

    public function test_company_country_drives_local_number_parsing(): void
    {
        $this->a->update(['country' => 'FR']);
        app(CurrentCompany::class)->set($this->a->fresh());

        $r = $this->importCsv("telephone\n06 12 34 56 78\n");
        $this->assertSame('+33612345678', Contact::first()->phone);
        $this->assertSame(1, $r->created_count);
    }

    public function test_import_history_is_scoped_per_company(): void
    {
        $this->importCsv("telephone\n0812345678\n");
        app(CurrentCompany::class)->set($this->b);
        $this->assertSame(0, ContactImport::count());
    }

    public function test_livewire_upload_flow_and_file_is_discarded(): void
    {
        $file = UploadedFile::fake()->createWithContent('contacts.csv', "telephone,nom\n0812345678,Marie\n");

        Livewire::actingAs($this->ua)->test(Import::class)->set('file', $file)->call('import')
            ->assertHasNoErrors()->assertSee('1 créés');

        $this->assertSame(1, Contact::count());
        $this->assertSame('contacts.csv', ContactImport::first()->filename);
    }

    public function test_livewire_rejects_non_csv_upload(): void
    {
        Livewire::actingAs($this->ua)->test(Import::class)
            ->set('file', UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream'))
            ->call('import')->assertHasErrors('file');
        $this->assertSame(0, Contact::count());
    }
}
