<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\User;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** D10 : quand la 2FA est obligatoire et pas encore activée, la page d'activation passe en tête et la navigation reste utilisable. */
class ForcedTwoFactorLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create();
        Company::createFor($u, 'Acme');

        return $u;
    }

    private function enableTwoFactor(User $u): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:10'));
        $service = app(TwoFactor::class);
        $service->beginSetup($u);
        $service->confirm($u, Totp::at($u->fresh()->two_factor_secret, now()->timestamp));
        Carbon::setTestNow();
    }

    public function test_forced_setup_page_puts_activation_first_and_hides_unusable_links(): void
    {
        config(['security.require_2fa_for_admins' => true]);
        $html = $this->actingAs($this->owner())->get('/profil')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Changer le mot de passe'), strpos($html, 'Double authentification (TOTP)'), 'la 2FA doit venir avant le mot de passe');
        $this->assertStringContainsString('ring-2', $html); // section mise en évidence
        $this->assertStringContainsString('Elle est obligatoire pour votre rôle', $html);
        foreach (['>Contacts<', '>Campagnes<', '>Rapports<', '>Membres<', '>Crédits<'] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link ne doit pas être proposé tant que la 2FA n'est pas activée");
        }
        $this->assertStringContainsString('>Profil<', $html);
        $this->assertStringContainsString('Déconnexion', $html);
    }

    public function test_forced_platform_admin_does_not_see_administration_links_yet(): void
    {
        config(['security.require_2fa_for_admins' => true]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)->get('/profil')->assertOk()->assertDontSee('Administration')->assertSee('>Profil<', false);
    }

    public function test_normal_order_and_full_navigation_once_two_factor_is_enabled(): void
    {
        config(['security.require_2fa_for_admins' => true]);
        $u = $this->owner();
        $this->enableTwoFactor($u);

        $html = $this->actingAs($u)->get('/profil')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Double authentification (TOTP)'), strpos($html, 'Changer le mot de passe'));
        $this->assertStringNotContainsString('ring-2', $html);
        $this->assertStringContainsString('>Contacts<', $html);
    }

    public function test_nothing_changes_when_the_requirement_is_off(): void
    {
        config(['security.require_2fa_for_admins' => false]);
        $html = $this->actingAs($this->owner())->get('/profil')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Double authentification (TOTP)'), strpos($html, 'Changer le mot de passe'));
        $this->assertStringContainsString('>Contacts<', $html);
        $this->assertStringNotContainsString('ring-2', $html);
    }

    public function test_plain_members_are_never_forced(): void
    {
        config(['security.require_2fa_for_admins' => true]);
        $owner = $this->owner();
        $member = User::factory()->create();
        $owner->companies()->first()->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->get('/tableau-de-bord')->assertOk()->assertSee('>Contacts<', false);
    }
}
