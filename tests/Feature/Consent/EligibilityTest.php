<?php

namespace Tests\Feature\Consent;

use App\Domain\Consent\ConsentService;
use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EligibilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private ConsentService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::createFor(User::factory()->create(), 'A');
        $this->b = Company::createFor(User::factory()->create(), 'B');
        app(CurrentCompany::class)->set($this->a);
        $this->svc = app(ConsentService::class);
    }

    private function eligibilityOf(Contact $c): Eligibility
    {
        return app(SendEligibility::class)->for($c);
    }

    private function consent(Contact $c, string $when = '-1 day'): Consent
    {
        return $this->svc->record($c, 'web_form', 'Case cochée sur le formulaire X', now()->modify($when));
    }

    public function test_contact_without_consent_is_not_eligible(): void
    {
        $c = Contact::factory()->create();
        $this->assertSame(Eligibility::NoConsent, $this->eligibilityOf($c));
        $this->assertSame(0, Contact::sendable()->count());
    }

    public function test_contact_with_valid_consent_is_eligible_and_scope_agrees(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c);

        $this->assertSame(Eligibility::Eligible, $this->eligibilityOf($c));
        $this->assertSame([$c->id], Contact::sendable()->pluck('id')->all());
    }

    public function test_revoked_consent_blocks(): void
    {
        $c = Contact::factory()->create();
        $k = $this->consent($c);
        $this->svc->revoke($k, 'demande du client');

        $this->assertSame(Eligibility::NoConsent, $this->eligibilityOf($c));
        $this->assertSame(0, Contact::sendable()->count());
    }

    public function test_suppression_overrides_consent_and_takes_effect_immediately(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c);
        $this->assertSame(Eligibility::Eligible, $this->eligibilityOf($c));

        $this->svc->optOut($c->phone);

        $this->assertSame(Eligibility::Suppressed, $this->eligibilityOf($c));
        $this->assertSame(0, Contact::sendable()->count());
        $this->assertNotNull(Consent::first()->revoked_at); // consentements révoqués à la désinscription
    }

    public function test_suppression_blocks_even_if_a_consent_row_is_inserted_directly(): void
    {
        $c = Contact::factory()->create();
        $this->svc->optOut($c->phone);
        Consent::create(['contact_id' => $c->id, 'source' => 'web_form', 'obtained_at' => now()]); // contourne le service

        $this->assertSame(Eligibility::Suppressed, $this->eligibilityOf($c));
        $this->assertSame(0, Contact::sendable()->count());
    }

    public function test_suppression_survives_contact_deletion_and_recreation(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c);
        $this->svc->optOut($c->phone);
        $phone = $c->phone;
        $c->delete();

        $again = Contact::create(['phone' => $phone]);
        $this->assertSame(Eligibility::Suppressed, $this->eligibilityOf($again));
    }

    public function test_cannot_record_consent_for_suppressed_number_until_lifted_then_fresh_consent_is_required(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c);
        $s = $this->svc->optOut($c->phone);

        try {
            $this->consent($c);
            $this->fail('Exception attendue');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->svc->lift($s, 'Le client a redemandé à recevoir les SMS');
        $this->assertSame(Eligibility::NoConsent, $this->eligibilityOf($c)); // l'ancien consentement ne revit pas

        $this->consent($c);
        $this->assertSame(Eligibility::Eligible, $this->eligibilityOf($c));
    }

    public function test_lifting_requires_a_reason_and_optout_is_idempotent(): void
    {
        $c = Contact::factory()->create();
        $s = $this->svc->optOut($c->phone);
        $this->svc->optOut($c->phone);
        $this->assertSame(1, Suppression::active()->count());

        $this->expectException(InvalidArgumentException::class);
        $this->svc->lift($s, '  ');
    }

    public function test_consent_validation_rules(): void
    {
        $c = Contact::factory()->create();
        foreach ([
            ['inconnue', 'preuve', now()],
            ['web_form', '', now()],
            ['web_form', 'preuve', now()->addDay()],
        ] as [$src, $ev, $date]) {
            try {
                $this->svc->record($c, $src, $ev, $date);
                $this->fail('Exception attendue');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, Consent::count());
    }

    public function test_evidence_can_be_made_optional_by_configuration(): void
    {
        config(['consent.require_evidence' => false]);
        $c = Contact::factory()->create();
        $this->assertNotNull($this->svc->record($c, 'paper', null, now()));
    }

    public function test_consent_expires_when_max_age_is_configured(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c, '-400 days');
        $this->assertSame(Eligibility::Eligible, $this->eligibilityOf($c)); // pas d'expiration par défaut

        config(['consent.max_age_days' => 365]);
        $this->assertSame(Eligibility::NoConsent, $this->eligibilityOf($c));
        $this->assertSame(0, Contact::sendable()->count());
    }

    public function test_isolation_between_companies(): void
    {
        $ca = Contact::factory()->create(['phone' => '+243811111111']);
        $this->consent($ca);
        $this->svc->optOut('+243822222222'); // désinscrit chez A uniquement

        app(CurrentCompany::class)->set($this->b);
        $cb = Contact::create(['phone' => '+243822222222']);
        $this->consent($cb);

        // Le même numéro reste ciblable chez B : les désinscriptions de A ne fuient pas.
        $this->assertSame(Eligibility::Eligible, $this->eligibilityOf($cb));
        $this->assertSame(0, Suppression::count());
        $this->assertSame(1, Consent::count());

        app(CurrentCompany::class)->set($this->a);
        $this->assertSame(1, Suppression::count());
        $this->assertSame(1, Consent::count());
    }

    public function test_database_rejects_consent_linking_contacts_of_other_company(): void
    {
        $ca = Contact::factory()->create();
        $this->expectException(QueryException::class);
        \DB::table('consents')->insert([
            'company_id' => $this->b->id, 'contact_id' => $ca->id, 'source' => 'web_form', 'obtained_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_database_allows_only_one_active_suppression_per_number(): void
    {
        $this->svc->optOut('+243811111111');
        $this->expectException(QueryException::class);
        \DB::table('suppressions')->insert([
            'company_id' => $this->a->id, 'phone' => '+243811111111', 'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_eligibility_check_never_trusts_a_stale_instance(): void
    {
        $c = Contact::factory()->create();
        $this->consent($c);
        $stale = Contact::find($c->id);
        $this->svc->optOut($c->phone);

        $this->assertSame(Eligibility::Suppressed, $this->eligibilityOf($stale));
    }
}
