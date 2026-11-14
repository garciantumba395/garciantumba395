<?php

namespace Tests\Feature\Security;

use App\Livewire\Profile\Show;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($this->user, 'Acme');
    }

    public function test_guest_is_redirected_and_user_sees_only_own_profile(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));

        $other = User::factory()->create(['name' => 'Autre Personne', 'email' => 'autre@example.com']);
        $this->actingAs($this->user)->get(route('profile'))->assertOk()
            ->assertSee($this->user->email)->assertDontSee('autre@example.com')->assertSee('Mon profil');
        $this->assertNotNull($other);
    }

    public function test_profile_is_available_without_active_company(): void
    {
        $lone = User::factory()->create();
        $this->actingAs($lone)->get(route('profile'))->assertOk();
    }

    public function test_navigation_links_to_profile(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee(route('profile'), false);
    }

    public function test_password_change_succeeds_with_current_password(): void
    {
        $old = $this->user->password;
        Livewire::actingAs($this->user)->test(Show::class)
            ->set('currentPassword', 'mot-de-passe-solide-1')->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
            ->call('changePassword')->assertHasNoErrors()->assertSet('currentPassword', '')->assertSet('newPassword', '');

        $fresh = $this->user->fresh();
        $this->assertNotSame($old, $fresh->password);
        $this->assertTrue(Hash::check('nouveau-mot-de-passe-9', $fresh->password));
        $this->assertFalse(Hash::check('mot-de-passe-solide-1', $fresh->password));
    }

    public function test_wrong_current_password_is_refused_and_nothing_changes(): void
    {
        $old = $this->user->password;
        Livewire::actingAs($this->user)->test(Show::class)
            ->set('currentPassword', 'pas-le-bon-mot-de-passe')->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
            ->call('changePassword')->assertHasErrors('currentPassword');

        $this->assertSame($old, $this->user->fresh()->password);
    }

    public function test_new_password_policy_is_enforced(): void
    {
        $t = Livewire::actingAs($this->user)->test(Show::class);
        $t->set('currentPassword', 'mot-de-passe-solide-1')->set('newPassword', 'court')->set('newPassword_confirmation', 'court')
            ->call('changePassword')->assertHasErrors('newPassword');
        $t->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'different-mot-de-passe-9')
            ->call('changePassword')->assertHasErrors('newPassword');
        $t->set('newPassword', 'mot-de-passe-solide-1')->set('newPassword_confirmation', 'mot-de-passe-solide-1')
            ->call('changePassword')->assertHasErrors('newPassword'); // identique à l'actuel
        $this->assertTrue(Hash::check('mot-de-passe-solide-1', $this->user->fresh()->password));
    }

    public function test_password_change_invalidates_other_devices_but_keeps_this_one(): void
    {
        // Appareil B : session ouverte avec l'empreinte du mot de passe actuel.
        $this->actingAs($this->user)->get(route('profile'))->assertOk();
        $oldHash = session('password_hash_web');
        $this->assertNotEmpty($oldHash);
        $this->get(route('profile'))->assertOk();

        Livewire::actingAs($this->user)->test(Show::class)
            ->set('currentPassword', 'mot-de-passe-solide-1')->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
            ->call('changePassword')->assertHasNoErrors();

        // L'ancienne session (empreinte périmée) est déconnectée au prochain accès.
        $this->flushSession();
        $this->actingAs($this->user->fresh())->withSession(['password_hash_web' => $oldHash])->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_session_with_stale_password_hash_is_logged_out_by_middleware(): void
    {
        $this->actingAs($this->user)->withSession(['password_hash_web' => 'empreinte-perimee'])->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_other_devices_requires_password_and_rotates_the_session_hash(): void
    {
        $oldHash = $this->user->password;
        $t = Livewire::actingAs($this->user)->test(Show::class);

        $t->set('logoutPassword', 'mauvais-mot-de-passe')->call('logoutOtherDevices')->assertHasErrors('logoutPassword');
        $this->assertSame($oldHash, $this->user->fresh()->password);

        $t->set('logoutPassword', 'mot-de-passe-solide-1')->call('logoutOtherDevices')->assertHasNoErrors();
        $fresh = $this->user->fresh();
        $this->assertNotSame($oldHash, $fresh->password, 'l\'empreinte du mot de passe change : les autres sessions deviennent invalides');
        $this->assertTrue(Hash::check('mot-de-passe-solide-1', $fresh->password));
    }

    public function test_password_attempts_are_rate_limited(): void
    {
        config(['security.rate_limits.sensitive_profile' => ['max' => 3, 'decay' => 300]]);
        $t = Livewire::actingAs($this->user)->test(Show::class);

        for ($i = 0; $i < 3; $i++) {
            $t->set('currentPassword', 'faux-'.$i)->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
                ->call('changePassword')->assertHasErrors('currentPassword');
        }
        // Le bon mot de passe est lui aussi refusé tant que la limite est atteinte.
        $t->set('currentPassword', 'mot-de-passe-solide-1')->call('changePassword')->assertHasErrors('currentPassword');
        $this->assertTrue(Hash::check('mot-de-passe-solide-1', $this->user->fresh()->password));
    }

    public function test_limits_are_per_user(): void
    {
        config(['security.rate_limits.sensitive_profile' => ['max' => 1, 'decay' => 300]]);
        $b = User::factory()->create(['password' => 'mot-de-passe-solide-2']);

        Livewire::actingAs($this->user)->test(Show::class)->set('currentPassword', 'x')->call('changePassword');
        Livewire::actingAs($this->user)->test(Show::class)->set('currentPassword', 'x')->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
            ->call('changePassword')->assertHasErrors('currentPassword');
        Livewire::actingAs($b)->test(Show::class)
            ->set('currentPassword', 'mot-de-passe-solide-2')->set('newPassword', 'nouveau-mot-de-passe-9')->set('newPassword_confirmation', 'nouveau-mot-de-passe-9')
            ->call('changePassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('nouveau-mot-de-passe-9', $b->fresh()->password));
    }
}
