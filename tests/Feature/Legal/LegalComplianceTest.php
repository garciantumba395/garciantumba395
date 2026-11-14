<?php

namespace Tests\Feature\Legal;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Consent\ConsentService;
use App\Domain\Consent\Eligibility;
use App\Domain\Consent\SendEligibility;
use App\Domain\Credits\CostCalculator;
use App\Domain\Credits\CreditService;
use App\Domain\Credits\SegmentCalculator;
use App\Domain\Legal\MessageComposer;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsStatus;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CreditLedgerEntry;
use App\Models\SmsMessage;
use App\Models\UnsubscribeLink;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CountingLiveProvider;
use Tests\TestCase;

/**
 * D4 — Code du numérique RDC (ordonnance-loi n° 23/010) : art. 66 (publicité identifiée, expéditeur identifiable), 72 (moyen gratuit d'arrêter),
 * 348 (lien de désabonnement). Voir docs/CADRE_LEGAL_RDC.md.
 */
class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Company $co;

    private User $owner;

    private MessageComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Boutique Démo');
        $this->co->update(['legal_name' => 'Boutique Démo SARL', 'legal_address' => '12 avenue du Commerce, Kinshasa', 'legal_email' => 'contact@boutique.example.test']);
        $this->co = $this->co->fresh();
        app(CreditService::class)->credit($this->co, 60000000, 'seed');
        app(CurrentCompany::class)->set($this->co);
        $this->composer = app(MessageComposer::class);
    }

    private function contact(string $phone = '+243810000001', bool $consent = true): Contact
    {
        $c = Contact::create(['phone' => $phone]);
        if ($consent) {
            app(ConsentService::class)->record($c, 'paper', 'preuve', now()->subDay());
        }

        return $c;
    }

    private function campaign(string $body = 'Promo -20 %'): Campaign
    {
        $camp = new Campaign(['name' => 'Promo', 'body' => $body]);
        $camp->created_by = $this->owner->id;
        $camp->save();

        return $camp;
    }

    // --- Pied de message (art. 66, 72, 348) -----------------------------------------------------------

    public function test_footer_identifies_advertising_sender_and_gives_an_unsubscribe_link(): void
    {
        $text = $this->composer->compose('Bonjour, -20 % ce week-end.', $this->contact(), $this->co);
        $link = UnsubscribeLink::first();

        $this->assertStringStartsWith('Bonjour, -20 % ce week-end.', $text);
        $this->assertStringContainsString('Publicité', $text);                          // art. 66
        $this->assertStringContainsString('Boutique Démo SARL', $text);                 // expéditeur identifiable (nom légal)
        $this->assertStringContainsString('Désabonnement', $text);                      // art. 72
        $this->assertStringContainsString(route('unsubscribe.show', $link->code), $text); // art. 348 : un LIEN
        $this->assertSame(10, strlen($link->code));
    }

    public function test_sender_name_falls_back_to_the_company_name(): void
    {
        $this->co->update(['legal_name' => null]);

        $this->assertStringContainsString('Publicité - Boutique Démo.', $this->composer->compose('x', $this->contact(), $this->co->fresh()));
    }

    public function test_the_link_is_stable_per_recipient_and_distinct_between_recipients_and_companies(): void
    {
        $a = $this->contact('+243810000001');
        $b = $this->contact('+243810000002');

        $first = $this->composer->compose('x', $a, $this->co);
        $again = $this->composer->compose('x', $a, $this->co);
        $other = $this->composer->compose('x', $b, $this->co);

        $this->assertSame($first, $again);
        $this->assertNotSame($first, $other);
        $this->assertSame(2, UnsubscribeLink::count());

        $otherCo = Company::createFor(User::factory()->create(), 'Autre');
        $theirs = app(CurrentCompany::class)->runAs($otherCo, fn () => $this->composer->compose('x', Contact::create(['phone' => '+243810000001']), $otherCo));
        $this->assertNotSame($first, $theirs); // même numéro, autre entreprise : autre lien
        $this->assertSame(3, UnsubscribeLink::count());
    }

    public function test_estimate_has_no_side_effect_and_matches_the_real_message_length(): void
    {
        $contact = $this->contact();
        $estimate = $this->composer->composeForEstimate('Promo', $this->co);
        $this->assertSame(0, UnsubscribeLink::count());

        $real = $this->composer->compose('Promo', $contact, $this->co);
        $this->assertSame(mb_strlen($estimate), mb_strlen($real));
        $this->assertSame($this->composer->footerLength($this->co) + mb_strlen('Promo'), mb_strlen($estimate));
    }

    public function test_the_footer_stays_in_the_gsm7_alphabet_for_ordinary_names(): void
    {
        $a = app(SegmentCalculator::class)->analyze($this->composer->composeForEstimate('Promo', $this->co));
        $this->assertSame('gsm7', $a['encoding']); // « é » est dans l'alphabet GSM-7 : pas de passage en Unicode (donc pas de surcoût)

        $this->co->update(['legal_name' => 'Chez Françoise']);   // « ç » force l'Unicode
        $b = app(SegmentCalculator::class)->analyze($this->composer->composeForEstimate('Promo', $this->co->fresh()));
        $this->assertSame('ucs2', $b['encoding']);
    }

    public function test_enforcement_can_be_switched_off_only_by_configuration(): void
    {
        config(['legal.enforce' => false]);

        $this->assertSame('Promo', $this->composer->compose('Promo', $this->contact(), $this->co));
        $this->assertSame('Promo', $this->composer->composeForEstimate('Promo', $this->co));
        $this->assertSame(0, UnsubscribeLink::count());
    }

    // --- Intégration campagne : texte réellement envoyé, coût cohérent ----------------------------------------

    public function test_campaign_messages_carry_the_legal_footer_and_the_recipient_own_link(): void
    {
        $this->contact('+243810000001');
        $this->contact('+243810000002');
        $camp = $this->campaign('Promo');

        app(CampaignService::class)->launch($camp);

        $bodies = SmsMessage::orderBy('id')->pluck('body', 'phone');
        $this->assertCount(2, $bodies);
        foreach ($bodies as $phone => $body) {
            $code = UnsubscribeLink::where('phone', $phone)->value('code');
            $this->assertStringContainsString('Publicité - Boutique Démo SARL', $body);
            $this->assertStringContainsString(route('unsubscribe.show', $code), $body);
        }
        $this->assertNotSame($bodies['+243810000001'], $bodies['+243810000002']);
    }

    public function test_estimated_cost_equals_the_actual_cost_including_the_footer(): void
    {
        foreach (range(1, 5) as $i) {
            $this->contact('+2438100001'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $camp = $this->campaign(str_repeat('a', 120)); // 120 + pied légal : franchit les 160 caractères → 2 segments
        $service = app(CampaignService::class);

        $estimate = $service->estimate($camp);
        $service->launch($camp);
        $debited = -(int) CreditLedgerEntry::where('type', 'debit')->sum('amount_units');

        $this->assertSame(5, $estimate['recipients']);
        $this->assertSame($estimate['cost_units'], $debited, "l'estimation doit égaler le coût réel");
        $this->assertSame(2, (int) SmsMessage::first()->segments);
        $this->assertGreaterThan(5 * 5000, $debited); // plus cher qu'un seul segment par message : le pied légal consomme des caractères
    }

    public function test_segments_are_computed_on_the_final_text_not_on_the_typed_body(): void
    {
        $this->contact();
        $camp = $this->campaign('Promo');
        app(CampaignService::class)->launch($camp);

        $m = SmsMessage::first();
        $this->assertSame(app(SegmentCalculator::class)->analyze($m->body)['segments'], (int) $m->segments);
        $this->assertSame(app(CostCalculator::class)->forMessage($m->phone, $m->body)->totalUnits(), (int) $m->cost_units);
    }

    // --- Identité légale obligatoire pour l'envoi RÉEL (art. 66) ------------------------------------------------------

    private function goLive(): CountingLiveProvider
    {
        $live = new CountingLiveProvider;
        app(SmsManager::class)->extend('live-test', fn () => $live);
        config(['sms.provider' => 'live-test', 'sms.sending_enabled' => true, 'sms.sending.probation_daily_limit' => 100000]);

        return $live;
    }

    public function test_real_sending_is_paused_while_the_legal_identity_is_incomplete_then_resumes(): void
    {
        $live = $this->goLive();
        $this->co->update(['legal_address' => null]);
        app(CurrentCompany::class)->set($this->co->fresh());
        $this->contact();
        $camp = $this->campaign();

        app(CampaignService::class)->launch($camp);

        $camp->refresh();
        $this->assertSame(CampaignStatus::Paused, $camp->status);
        $this->assertSame('legal_identity_incomplete', $camp->pause_code);
        $this->assertSame(0, $live->calls);
        $this->assertSame(0, SmsMessage::count());
        $this->assertStringContainsString('art. 66', $camp->pause_message);

        $this->co->update(['legal_address' => '12 avenue du Commerce, Kinshasa']);
        app(CurrentCompany::class)->set($this->co->fresh());
        $this->assertTrue(app(CampaignService::class)->resume($camp->fresh()));

        $this->assertSame(CampaignStatus::Completed, $camp->fresh()->status);
        $this->assertSame(1, $live->calls);
        $this->assertSame(0, UnsubscribeLink::where('phone', '!=', '+243810000001')->count());
    }

    public function test_simulation_is_not_blocked_by_an_incomplete_identity(): void
    {
        $this->co->update(['legal_name' => null, 'legal_address' => null, 'legal_email' => null]);
        app(CurrentCompany::class)->set($this->co->fresh());
        $this->contact();

        app(CampaignService::class)->launch($this->campaign());

        $this->assertSame(1, SmsMessage::where('status', SmsStatus::Sent->value)->count());
        $this->assertTrue((bool) SmsMessage::first()->simulated);
    }

    public function test_identity_completeness_rules(): void
    {
        $c = new Company(['name' => 'X']);
        $this->assertFalse($c->hasCompleteLegalIdentity());
        foreach ([['legal_name' => 'N'], ['legal_name' => 'N', 'legal_address' => 'A'], ['legal_name' => 'N', 'legal_email' => 'e@x.test'], ['legal_name' => ' ', 'legal_address' => 'A', 'legal_email' => 'e@x.test']] as $partial) {
            $this->assertFalse((new Company(['name' => 'X'] + $partial))->hasCompleteLegalIdentity(), json_encode($partial));
        }
        // RCCM, numéro d'impôt et acte : « éventuellement » (art. 66) : non exigés.
        $this->assertTrue((new Company(['name' => 'X', 'legal_name' => 'N', 'legal_address' => 'A', 'legal_email' => 'e@x.test']))->hasCompleteLegalIdentity());
    }

    // --- security:check ----------------------------------------------------------------------------------------------------

    public function test_security_check_blocks_a_production_without_the_legal_footer(): void
    {
        config(['app.env' => 'production', 'legal.enforce' => false]);

        $this->assertSame(1, Artisan::call('security:check'));
        $this->assertStringContainsString('LEGAL_ENFORCE', Artisan::output());
    }

    public function test_the_shipped_default_enforces_the_legal_footer(): void
    {
        $this->assertStringContainsString("env('LEGAL_ENFORCE', true)", (string) file_get_contents(base_path('config/legal.php')));
    }

    // --- Désabonnement et envoi suivant (art. 70, 213) -----------------------------------------------------------------------

    public function test_after_unsubscribing_through_the_link_the_next_campaign_does_not_reach_that_contact(): void
    {
        $a = $this->contact('+243810000001');
        $b = $this->contact('+243810000002');
        app(CampaignService::class)->launch($this->campaign('Première'));
        $code = UnsubscribeLink::where('phone', $a->phone)->value('code');

        $this->post(route('unsubscribe.confirm', $code))->assertOk();

        $this->assertSame(Eligibility::Suppressed, app(SendEligibility::class)->for($a->fresh()));
        $this->assertSame(Eligibility::Eligible, app(SendEligibility::class)->for($b->fresh()));
        app(CampaignService::class)->launch($this->campaign('Seconde'));
        $this->assertSame(1, SmsMessage::where('phone', $a->phone)->where('status', SmsStatus::Sent->value)->count());   // seulement la 1re
        $this->assertSame(2, SmsMessage::where('phone', $b->phone)->where('status', SmsStatus::Sent->value)->count());
    }
}
