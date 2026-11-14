<?php

namespace Tests\Feature\Campaigns;

use App\Domain\Campaigns\CampaignService;
use App\Domain\Campaigns\CampaignStatus;
use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Credits\SegmentCalculator;
use App\Domain\Legal\MessageComposer;
use App\Enums\CompanyRole;
use App\Livewire\Campaigns\Form;
use App\Livewire\Campaigns\Index;
use App\Livewire\Campaigns\Show;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignUiTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $owner;

    private User $member;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->a = Company::createFor($this->owner, 'A');
        $this->b = Company::createFor($this->other, 'B');
        $this->member = User::factory()->create();
        $this->a->users()->attach($this->member, ['role' => CompanyRole::Member->value]);
        app(CreditService::class)->credit($this->a, 6000000, 'seed');
        app(CreditService::class)->credit($this->b, 6000000, 'seed');
    }

    private function in(Company $c, callable $fn): mixed
    {
        return app(CurrentCompany::class)->runAs($c, $fn);
    }

    private function consenting(Company $c, string $phone): Contact
    {
        return $this->in($c, function () use ($phone) {
            $k = Contact::create(['phone' => $phone]);
            app(ConsentService::class)->record($k, 'paper', 'x', now()->subDay());

            return $k;
        });
    }

    private function draft(Company $c, User $by, string $name = 'Promo', string $body = 'Bonjour'): Campaign
    {
        return $this->in($c, function () use ($by, $name, $body) {
            $x = new Campaign(['name' => $name, 'body' => $body]);
            $x->created_by = $by->id;
            $x->save();

            return $x;
        });
    }

    /** Segments du texte FINAL (corps + pied légal) : calculés par les mêmes services que l'application, donc indépendants de la longueur d'APP_URL. */
    private function expectedSegments(string $body, Company $company): int
    {
        return app(SegmentCalculator::class)->analyze(app(MessageComposer::class)->composeForEstimate($body, $company))['segments'];
    }

    public function test_member_can_create_a_draft_with_live_segment_count(): void
    {
        Livewire::actingAs($this->member)->test(Form::class)
            ->set('name', 'Soldes')->set('body', str_repeat('ç', 71))->assertSee($this->expectedSegments(str_repeat('ç', 71), $this->a).' segment(s)')->assertSee('pied légal')
            ->call('save')->assertHasNoErrors();

        $c = $this->in($this->a, fn () => Campaign::first());
        $this->assertSame(CampaignStatus::Draft, $c->status);
        $this->assertSame($this->member->id, $c->created_by);
    }

    public function test_form_validation_and_foreign_group_rejected(): void
    {
        $foreign = $this->in($this->b, fn () => ContactGroup::create(['name' => 'B']));

        Livewire::actingAs($this->owner)->test(Form::class)->set('name', '')->set('body', '')->call('save')->assertHasErrors(['name', 'body']);
        Livewire::actingAs($this->owner)->test(Form::class)->set('name', 'X')->set('body', 'Y')->set('groupId', $foreign->id)->call('save')->assertHasErrors('groupId');
        Livewire::actingAs($this->owner)->test(Form::class)->set('name', 'X')->set('body', 'Y')->set('scheduledAt', now()->subDay()->format('Y-m-d\TH:i'))->call('save')->assertHasErrors('scheduledAt');
        $this->assertSame(0, Campaign::withoutGlobalScopes()->count());
    }

    public function test_scheduled_time_is_interpreted_in_the_company_timezone(): void
    {
        $when = now()->addDay()->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i');

        Livewire::actingAs($this->owner)->test(Form::class)->set('name', 'X')->set('body', 'Y')->set('scheduledAt', $when)->call('save')->assertHasNoErrors();

        $stored = $this->in($this->a, fn () => Campaign::first()->scheduled_at);
        $this->assertSame($when, $stored->timezone(config('app.timezone'))->format('Y-m-d\TH:i'));
    }

    public function test_scheduled_campaign_starts_at_the_wall_clock_time_the_user_entered(): void
    {
        $this->consenting($this->a, '+243811000001');
        $when = now()->addHours(2)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i');
        Livewire::actingAs($this->owner)->test(Form::class)->set('name', 'X')->set('body', 'Y')->set('scheduledAt', $when)->call('save')->assertHasNoErrors();
        $campaign = Campaign::withoutGlobalScopes()->first();
        $service = app(CampaignService::class);
        $this->in($this->a, fn () => $service->launch($campaign, $campaign->scheduled_at));

        $this->travel(119)->minutes(); // avant l'heure saisie : rien ne part (au plus 1 minute d'écart avec la saisie tronquée)
        $this->assertSame(0, $service->dispatchDue());
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());

        $this->travel(3)->minutes(); // après l'heure saisie
        $this->assertSame(1, $service->dispatchDue());
        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $d = $this->draft($this->a, $this->owner);
        Livewire::actingAs($this->owner)->test(Form::class, ['campaign' => $d->id])->assertSet('name', 'Promo');

        Campaign::withoutGlobalScopes()->whereKey($d->id)->update(['status' => 'completed']);
        Livewire::actingAs($this->owner)->test(Form::class, ['campaign' => $d->id])->assertForbidden();
        Livewire::actingAs($this->owner)->test(Index::class)->call('delete', $d->id)->assertForbidden();
    }

    public function test_member_cannot_launch_pause_resume_or_cancel(): void
    {
        $d = $this->draft($this->a, $this->owner);
        $this->consenting($this->a, '+243811000001');

        foreach (['launch', 'pause', 'resume', 'cancel'] as $action) {
            Livewire::actingAs($this->member)->test(Show::class, ['campaign' => $d->id])->call($action)->assertForbidden();
        }
        $this->assertSame(CampaignStatus::Draft, Campaign::withoutGlobalScopes()->find($d->id)->status);
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_owner_launches_and_sees_real_counters_marked_as_simulation(): void
    {
        $this->consenting($this->a, '+243811000001');
        $this->consenting($this->a, '+243811000002');
        $d = $this->draft($this->a, $this->owner);

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $d->id])
            ->assertSee('Estimation avant lancement')->assertSee('2 destinataire(s) ciblable(s)')
            ->call('launch')->assertHasNoErrors()
            ->assertSee('Terminée')->assertSee('simulation')->assertSee('Mode simulation');

        $c = Campaign::withoutGlobalScopes()->find($d->id);
        $this->assertSame(CampaignStatus::Completed, $c->status);
        $this->assertSame(2, SmsMessage::withoutGlobalScopes()->where('status', 'sent')->count());
    }

    public function test_launch_without_recipients_shows_error(): void
    {
        $d = $this->draft($this->a, $this->owner);

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $d->id])->call('launch')->assertSee('Aucun destinataire ciblable');
        $this->assertSame(CampaignStatus::Draft, Campaign::withoutGlobalScopes()->find($d->id)->status);
    }

    public function test_insufficient_balance_warning_and_pause_banner(): void
    {
        $this->consenting($this->a, '+243811000001');
        app(CreditService::class)->adjust($this->a, -6000000, 'drain', 'test');
        $d = $this->draft($this->a, $this->owner);

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $d->id])->assertSee('Solde insuffisant')
            ->call('launch')->assertSee('Campagne en pause')->assertSee('Crédits insuffisants')->assertSee('Reprendre');
    }

    public function test_cancel_from_ui(): void
    {
        $this->consenting($this->a, '+243811000001');
        $d = $this->draft($this->a, $this->owner);
        Campaign::withoutGlobalScopes()->whereKey($d->id)->update(['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);

        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $d->id])->call('cancel')->assertSee('Annulée');
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->count());
    }

    public function test_isolation_between_companies(): void
    {
        $mine = $this->draft($this->a, $this->owner, 'Campagne A');
        $theirs = $this->draft($this->b, $this->other, 'Secret de B');

        Livewire::actingAs($this->owner)->test(Index::class)->assertSee('Campagne A')->assertDontSee('Secret de B');
        $this->actingAs($this->owner)->get(route('campaigns.show', $theirs->id))->assertNotFound();
        $this->actingAs($this->owner)->get(route('campaigns.edit', $theirs->id))->assertNotFound();
        Livewire::actingAs($this->owner)->test(Show::class, ['campaign' => $theirs->id])->assertNotFound();
        Livewire::actingAs($this->owner)->test(Index::class)->call('delete', $theirs->id)->assertNotFound();
        $this->assertNotNull($mine);
        $this->assertSame(CampaignStatus::Draft, Campaign::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_guests_are_redirected(): void
    {
        foreach (['/campagnes', '/campagnes/nouvelle', '/campagnes/1'] as $u) {
            $this->get($u)->assertRedirect('/connexion');
        }
    }
}
