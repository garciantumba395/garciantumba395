<?php

namespace Tests\Feature\Legal;

use App\Domain\Consent\ConsentService;
use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
use App\Domain\Legal\UnsubscribeLinks;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use App\Models\UnsubscribeLink;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Page PUBLIQUE de désabonnement (art. 70, 72, 213, 348 du Code du numérique). */
class UnsubscribePageTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::createFor(User::factory()->create(), 'Alpha');
        $this->a->update(['legal_name' => 'Alpha SARL', 'legal_address' => '1 rue A, Kinshasa', 'legal_email' => 'a@alpha.example.test',
            'legal_phone' => '+243810009999', 'legal_rccm' => 'CD/KIN/RCCM/00-0000', 'legal_tax_id' => 'A0000000X', 'legal_act' => 'Arrêté n° 123']);
        $this->b = Company::createFor(User::factory()->create(), 'Bravo');
        RateLimiter::clear('unsub|127.0.0.1');
    }

    /** @return array{0:Contact,1:string} contact consentant et son code de désabonnement */
    private function recipient(Company $company, string $phone = '+243810000001'): array
    {
        return app(CurrentCompany::class)->runAs($company, function () use ($phone) {
            $c = Contact::create(['phone' => $phone]);
            app(ConsentService::class)->record($c, 'paper', 'preuve', now()->subDay());

            return [$c, app(UnsubscribeLinks::class)->forContact($c)->code];
        });
    }

    private function eligibility(Company $company, string $phone): Eligibility
    {
        return app(CurrentCompany::class)->runAs($company, fn () => app(SendEligibility::class)->for(Contact::where('phone', $phone)->firstOrFail()));
    }

    // --- GET : affichage seul ------------------------------------------------------------------------------------

    public function test_get_shows_the_full_sender_identity_and_does_not_unsubscribe(): void
    {
        [$c, $code] = $this->recipient($this->a);

        $this->get("/d/$code")->assertOk()
            ->assertSee('Se désabonner')->assertSee('Alpha SARL')->assertSee('1 rue A, Kinshasa')->assertSee('a@alpha.example.test')
            ->assertSee('CD/KIN/RCCM/00-0000')->assertSee('A0000000X')->assertSee('Arrêté n° 123')->assertSee('gratuit');

        // Les messageries et antivirus préchargent les liens : un simple chargement ne doit JAMAIS désinscrire.
        $this->get("/d/$code");
        $this->head("/d/$code");
        $this->assertSame(0, Suppression::withoutGlobalScopes()->count());
        $this->assertNull(UnsubscribeLink::first()->used_at);
        $this->assertSame(Eligibility::Eligible, $this->eligibility($this->a, $c->phone));
    }

    public function test_optional_identity_fields_are_hidden_when_empty(): void
    {
        $this->a->update(['legal_phone' => null, 'legal_rccm' => null, 'legal_tax_id' => null, 'legal_act' => null]);
        [, $code] = $this->recipient($this->a);

        $this->get("/d/$code")->assertOk()->assertDontSee('RCCM')->assertDontSee("Numéro d'impôt")->assertDontSee('Acte autorisant');
    }

    public function test_page_is_public_not_cacheable_and_not_indexable(): void
    {
        [, $code] = $this->recipient($this->a);

        $this->get("/d/$code")->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('noindex', false);
        $this->assertStringContainsString('no-store', (string) $this->get("/d/$code")->headers->get('Cache-Control'));
        $this->assertGuest();
    }

    // --- POST : désinscription immédiate, accusé de réception -----------------------------------------------------

    public function test_post_unsubscribes_immediately_revokes_consent_and_acknowledges(): void
    {
        [$c, $code] = $this->recipient($this->a);

        $this->post("/d/$code")->assertOk()->assertSee('Désabonnement enregistré')->assertSee('accusé de réception')->assertSee('Alpha SARL');

        $this->assertSame(Eligibility::Suppressed, $this->eligibility($this->a, $c->phone));
        $this->assertSame(1, Suppression::withoutGlobalScopes()->where('company_id', $this->a->id)->where('phone', $c->phone)->whereNull('lifted_at')->count());
        $this->assertSame('unsubscribe_link', Suppression::withoutGlobalScopes()->first()->source);
        $this->assertNotNull(Consent::withoutGlobalScopes()->first()->revoked_at);   // le consentement est révoqué
        $this->assertNotNull(UnsubscribeLink::first()->used_at);
    }

    public function test_unsubscribing_twice_is_harmless_and_the_page_remembers(): void
    {
        [, $code] = $this->recipient($this->a);

        $this->post("/d/$code")->assertOk();
        $this->post("/d/$code")->assertOk()->assertSee('Désabonnement enregistré');
        $this->get("/d/$code")->assertOk()->assertSee('déjà désabonné');

        $this->assertSame(1, Suppression::withoutGlobalScopes()->count());
        $this->assertSame(1, AuditLog::where('action', 'contact.unsubscribed')->count()); // une seule entrée d'audit
    }

    public function test_the_audit_entry_contains_no_phone_number(): void
    {
        [$c, $code] = $this->recipient($this->a);
        $this->post("/d/$code");

        $log = AuditLog::where('action', 'contact.unsubscribed')->firstOrFail();

        $this->assertNull($log->actor_id);
        $this->assertSame($this->a->id, $log->company_id);
        $this->assertStringNotContainsString(ltrim($c->phone, '+'), json_encode($log->details).json_encode($log->toArray()));
    }

    public function test_unsubscribing_works_even_if_the_contact_was_deleted_or_the_company_is_inactive(): void
    {
        [$c, $code] = $this->recipient($this->a);
        app(CurrentCompany::class)->runAs($this->a, fn () => $c->delete());
        $this->a->update(['is_active' => false]);

        $this->post("/d/$code")->assertOk()->assertSee('Désabonnement enregistré');

        $this->assertSame(1, Suppression::withoutGlobalScopes()->where('phone', '+243810000001')->count());
    }

    // --- Isolation et sécurité des codes ----------------------------------------------------------------------------------

    public function test_a_link_only_affects_its_own_company_even_for_the_same_phone_number(): void
    {
        [, $codeA] = $this->recipient($this->a, '+243810000001');
        [$cb] = $this->recipient($this->b, '+243810000001');

        $this->post("/d/$codeA")->assertOk();

        $this->assertSame(Eligibility::Suppressed, $this->eligibility($this->a, '+243810000001'));
        $this->assertSame(Eligibility::Eligible, $this->eligibility($this->b, $cb->phone));
        $this->assertSame(0, Suppression::withoutGlobalScopes()->where('company_id', $this->b->id)->count());
    }

    public function test_a_link_of_the_second_company_affects_only_that_company(): void
    {
        // Sens inverse du test précédent : un lien qui n'appartient pas à la première entreprise ne doit jamais désinscrire chez elle.
        [$ca] = $this->recipient($this->a, '+243810000001');
        [$cb, $codeB] = $this->recipient($this->b, '+243810000001');

        $this->post("/d/$codeB")->assertOk()->assertSee('Bravo');

        $this->assertSame(Eligibility::Suppressed, $this->eligibility($this->b, $cb->phone));
        $this->assertSame(Eligibility::Eligible, $this->eligibility($this->a, $ca->phone));
        $this->assertSame([$this->b->id], Suppression::withoutGlobalScopes()->pluck('company_id')->all());
        $this->assertSame($this->b->id, AuditLog::where('action', 'contact.unsubscribed')->value('company_id'));
    }

    public function test_unknown_malformed_or_guessed_codes_give_404_and_change_nothing(): void
    {
        [$c] = $this->recipient($this->a);

        foreach (['AAAAAAAAAA', 'zzzzzzzzzz', 'abc', "bad'code0000", str_repeat('A', 40)] as $code) {
            $this->get("/d/$code")->assertNotFound();
            $this->post("/d/$code")->assertNotFound();
        }

        $this->assertSame(0, Suppression::withoutGlobalScopes()->count());
        $this->assertSame(Eligibility::Eligible, $this->eligibility($this->a, $c->phone));
    }

    public function test_the_phone_number_is_never_exposed_by_the_page_or_the_link(): void
    {
        [$c, $code] = $this->recipient($this->a);

        $html = $this->get("/d/$code")->getContent().$this->post("/d/$code")->getContent();

        $this->assertStringNotContainsString(ltrim($c->phone, '+'), $html);
        $this->assertStringNotContainsString($c->phone, $code);
    }

    public function test_codes_are_random_alphanumeric_and_unique(): void
    {
        $codes = collect(range(1, 40))->map(fn ($i) => $this->recipient($this->a, '+2438100'.str_pad((string) $i, 5, '0', STR_PAD_LEFT))[1]);

        $this->assertCount(40, $codes->unique());
        $codes->each(fn ($c) => $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $c));
    }

    public function test_the_public_endpoints_are_rate_limited(): void
    {
        config(['legal.unsubscribe.rate_limit_per_minute' => 3]);
        RateLimiter::clear('unsub|127.0.0.1');
        [, $code] = $this->recipient($this->a);

        foreach (range(1, 3) as $_) {
            $this->get("/d/$code")->assertOk();
        }
        $this->get("/d/$code")->assertStatus(429);
        $this->post("/d/$code")->assertStatus(429);
    }

    public function test_only_the_two_public_routes_exist_and_both_are_throttled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'd/'))->values();

        $this->assertSame(['d/{code}', 'd/{code}'], $routes->map->uri()->all());
        $this->assertSame(['GET', 'POST'], $routes->map(fn ($r) => collect($r->methods())->first(fn ($m) => in_array($m, ['GET', 'POST'], true)))->sort()->values()->all());
        $routes->each(fn ($r) => $this->assertContains('throttle:unsubscribe', $r->gatherMiddleware()));
    }
}
