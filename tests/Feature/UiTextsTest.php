<?php

namespace Tests\Feature;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Livewire\Campaigns\Show;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Textes d'interface : jamais d'affirmation périmée ou trompeuse (relevés lors d'une vérification dans un vrai navigateur). */
class UiTextsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->company = Company::createFor($this->owner, 'Acme');
        app(CreditService::class)->credit($this->company, 600000, 'seed');
    }

    private function campaign(): Campaign
    {
        return app(CurrentCompany::class)->runAs($this->company, function () {
            $c = Contact::create(['phone' => '+243810000001']);
            app(ConsentService::class)->record($c, 'paper', 'x', now()->subDay());
            $camp = new Campaign(['name' => 'Promo', 'body' => 'Bonjour']);
            $camp->created_by = $this->owner->id;
            $camp->save();

            return $camp;
        });
    }

    public function test_dashboard_no_longer_claims_features_are_coming_and_links_to_reports(): void
    {
        $this->actingAs($this->owner)->get('/tableau-de-bord')
            ->assertDontSee('prochaines étapes')->assertDontSee('seront ajoutés')
            ->assertSee('données réelles de votre entreprise')->assertSee(route('reports.index'), false);
    }

    public function test_launched_campaign_says_launched_not_scheduled(): void
    {
        $camp = $this->campaign();
        app(CurrentCompany::class)->runAs($this->company, fn () => app(CampaignService::class)->launch($camp));

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $camp->id])->assertSee('lancée le')->assertDontSee('programmée le');
    }

    public function test_future_campaign_says_scheduled(): void
    {
        $camp = $this->campaign();
        app(CurrentCompany::class)->runAs($this->company, fn () => app(CampaignService::class)->launch($camp, now()->addDay()));

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $camp->id])->assertSee('programmée le')->assertDontSee('lancée le');
    }

    public function test_default_application_name_is_not_the_framework_placeholder(): void
    {
        $this->assertSame('SMS Marketing', config('app.name'));
        $this->actingAs($this->owner)->get('/tableau-de-bord')->assertDontSee('>Laravel<', false);
    }
}
