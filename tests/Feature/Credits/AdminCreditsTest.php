<?php

namespace Tests\Feature\Credits;

use App\Domain\Credits\CreditService;
use App\Livewire\Admin\Companies;
use App\Livewire\Admin\Rates;
use App\Livewire\Credits\Index as CreditsPage;
use App\Models\Company;
use App\Models\CreditLedgerEntry;
use App\Models\SmsRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCreditsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $co;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->owner = User::factory()->create();
        $this->co = Company::createFor($this->owner, 'Cliente SARL');
    }

    private function balance(): int
    {
        return app(CreditService::class)->balance($this->co);
    }

    public function test_only_platform_admins_reach_admin_pages(): void
    {
        $this->get('/admin/entreprises')->assertRedirect('/connexion');
        $this->actingAs($this->owner)->get('/admin/entreprises')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/tarifs')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/entreprises')->assertOk()->assertSee('Cliente SARL');
        $this->actingAs($this->admin)->get('/admin/tarifs')->assertOk();
    }

    public function test_company_owner_role_does_not_grant_admin_rights(): void
    {
        Livewire::actingAs($this->owner)->test(Companies::class)->assertForbidden();
        Livewire::actingAs($this->owner)->test(Rates::class)->assertForbidden();
        $this->assertSame(0, $this->balance());
    }

    public function test_admin_adds_credits_from_decimal_amount(): void
    {
        Livewire::actingAs($this->admin)->test(Companies::class)
            ->set('companyId', $this->co->id)->set('amount', '100')->set('reason', 'Paiement reçu ref. 42')
            ->call('submit')->assertHasNoErrors()->assertSee('100,00');

        $this->assertSame(600000, $this->balance());
        $e = CreditLedgerEntry::withoutGlobalScopes()->first();
        $this->assertSame($this->admin->id, $e->created_by);
        $this->assertSame('Paiement reçu ref. 42', $e->reason);
    }

    public function test_double_submit_with_same_attempt_adds_credits_once(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Companies::class)
            ->set('companyId', $this->co->id)->set('amount', '10')->set('reason', 'Test');
        $key = $page->get('attemptKey');

        $page->call('submit');
        // Rejeu de la même tentative (double clic / requête rejouée) avec la même clé :
        app(CreditService::class)->credit($this->co, 60000, 'admin:'.$key, 'Test', $this->admin->id);

        $this->assertSame(60000, $this->balance());
        $this->assertNotSame($key, $page->get('attemptKey'));
    }

    public function test_admin_withdrawal_cannot_overdraw_and_needs_reason(): void
    {
        app(CreditService::class)->credit($this->co, 6000, 'seed');

        Livewire::actingAs($this->admin)->test(Companies::class)->set('companyId', $this->co->id)
            ->set('operation', 'retrait')->set('amount', '5')->set('reason', 'Correction')->call('submit')->assertHasErrors('amount')
            ->set('amount', '0.5')->set('reason', '')->call('submit')->assertHasErrors('reason');

        $this->assertSame(6000, $this->balance());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        foreach (['abc', '-5', '1.234', '0', '0.00', '99999999999'] as $bad) {
            Livewire::actingAs($this->admin)->test(Companies::class)->set('companyId', $this->co->id)
                ->set('amount', $bad)->set('reason', 'x')->call('submit')->assertHasErrors('amount');
        }
        $this->assertSame(0, $this->balance());
    }

    public function test_company_sees_only_its_own_balance_and_history(): void
    {
        $other = Company::createFor(User::factory()->create(), 'Autre');
        app(CreditService::class)->credit($this->co, 600000, 'a', 'Paiement A');
        app(CreditService::class)->credit($other, 60000, 'b', 'Paiement B secret');

        Livewire::actingAs($this->owner)->test(CreditsPage::class)
            ->assertSee('100,00')->assertSee('Paiement A')->assertDontSee('Paiement B secret')->assertDontSee('10,00');
    }

    public function test_dashboard_shows_real_balance(): void
    {
        $this->actingAs($this->owner)->get('/tableau-de-bord')->assertSee('Solde de crédits')->assertSee('0,00');
        app(CreditService::class)->credit($this->co, 750000, 'a');
        $this->actingAs($this->owner)->get('/tableau-de-bord')->assertSee('125,00');
    }

    public function test_rates_admin_creates_validates_and_toggles(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Rates::class)
            ->set('country', 'CD')->set('prefix', '+243 81')->set('label', 'Opérateur X')->set('smsPer100', '100')->call('add')->assertHasNoErrors();

        $rate = SmsRate::first();
        $this->assertSame(['CD', '+24381', 6000], [$rate->country, $rate->prefix, $rate->units_per_segment]);

        $page->set('prefix', '+24381')->set('smsPer100', '120')->call('add')->assertHasErrors('prefix');      // doublon
        $page->set('prefix', '')->set('smsPer100', '120')->call('add')->assertHasNoErrors();                  // tarif pays
        $page->set('prefix', '')->set('smsPer100', '130')->call('add')->assertHasErrors('country');           // 2e tarif pays
        $page->set('prefix', 'abc')->set('smsPer100', '120')->call('add')->assertHasErrors('prefix');
        $page->set('prefix', '+24382')->set('smsPer100', '0')->call('add')->assertHasErrors('smsPer100');
        $page->set('country', 'ZZ')->set('prefix', '')->set('smsPer100', '120')->call('add')->assertHasErrors('country');

        $page->call('toggle', $rate->id);
        $this->assertFalse($rate->fresh()->is_active);
        $this->assertSame(2, SmsRate::count());
    }

    public function test_admin_without_company_can_use_admin_area(): void
    {
        // L'administrateur de la plateforme n'a besoin d'aucune entreprise et n'accède à aucune donnée d'entreprise par ce statut.
        $this->actingAs($this->admin)->get('/admin/entreprises')->assertOk();
        $this->actingAs($this->admin)->get('/contacts')->assertRedirect(route('companies.create'));
    }
}
