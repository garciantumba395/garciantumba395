<?php

namespace Tests\Feature\Security;

use App\Domain\Consent\ConsentService;
use App\Livewire\Admin\Companies as AdminCompanies;
use App\Livewire\Admin\Rates as AdminRates;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Contacts\Import;
use App\Livewire\Messages\Index as MessageIndex;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Security\Throttle;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function limit(string $name, int $max): void
    {
        config(["security.rate_limits.$name" => ['max' => $max, 'decay' => 600]]);
    }

    public function test_throttle_counts_attempts_and_blocks_after_max(): void
    {
        $this->limit('csv_import', 2);
        $this->assertTrue(Throttle::attempt('csv_import', 'k'));
        $this->assertTrue(Throttle::attempt('csv_import', 'k'));
        $this->assertFalse(Throttle::attempt('csv_import', 'k'));
        $this->assertTrue(Throttle::attempt('csv_import', 'autre-cle'));
        $this->assertTrue(Throttle::tooMany('csv_import', 'k'));
        Throttle::clear('csv_import', 'k');
        $this->assertTrue(Throttle::attempt('csv_import', 'k'));
    }

    public function test_unknown_limiter_is_an_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Throttle::attempt('inexistant', 'k');
    }

    public function test_every_documented_limiter_has_a_configuration(): void
    {
        foreach (['login', 'register', 'password_forgot', 'two_factor_challenge', 'sensitive_profile', 'csv_import', 'test_send', 'admin_actions', 'livewire'] as $name) {
            $c = config("security.rate_limits.$name");
            $this->assertGreaterThan(0, $c['max'], $name);
            $this->assertGreaterThan(0, $c['decay'], $name);
        }
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        $this->limit('register', 2);
        $attempt = fn () => Livewire::test(Register::class)->set('name', 'X')->set('email', 'x@example.com')->set('password', 'court')->set('password_confirmation', 'court')
            ->set('company_name', 'Co')->call('register');

        $attempt()->assertHasErrors('password');
        $attempt()->assertHasErrors('password');
        $this->assertStringContainsString('Trop de tentatives', $attempt()->errors()->first('email'));
    }

    public function test_registration_still_works_under_the_limit(): void
    {
        Livewire::test(Register::class)->set('name', 'Alice')->set('email', 'alice@example.com')->set('password', 'mot-de-passe-solide-1')
            ->set('password_confirmation', 'mot-de-passe-solide-1')->set('company_name', 'Alice SARL')->call('register')->assertRedirect(route('dashboard'));
    }

    public function test_forgot_password_is_rate_limited_per_ip_and_email(): void
    {
        $this->limit('password_forgot', 2);
        $call = fn (string $email) => Livewire::test(ForgotPassword::class)->set('email', $email)->call('sendLink');

        $call('a@example.com')->assertSet('sent', true);
        $call('a@example.com')->assertSet('sent', true);
        $call('a@example.com')->assertSet('sent', false)->assertHasErrors('email');
        // Une autre adresse n'est pas bloquée par la première.
        $call('b@example.com')->assertSet('sent', true);
    }

    public function test_login_limit_by_email_and_ip_is_preserved(): void
    {
        $user = User::factory()->create(['password' => 'mot-de-passe-solide-1']);
        Company::createFor($user, 'Acme');
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', $user->email)->set('password', 'faux')->call('login')->assertHasErrors('email');
        }
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'mot-de-passe-solide-1')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_csv_import_is_rate_limited_per_user(): void
    {
        $this->limit('csv_import', 2);
        $a = User::factory()->create();
        Company::createFor($a, 'A');
        $b = User::factory()->create();
        Company::createFor($b, 'B');

        $call = fn (User $u) => Livewire::actingAs($u)->test(Import::class)->call('import');
        $call($a)->assertHasErrors('file');
        $call($a)->assertHasErrors('file');
        $third = $call($a)->assertHasErrors('file');
        $this->assertStringContainsString('Trop de tentatives', $third->errors()->first('file'));

        $other = $call($b); // un autre utilisateur n'est pas touché
        $this->assertStringNotContainsString('Trop de tentatives', $other->errors()->first('file'));
    }

    public function test_test_send_is_rate_limited(): void
    {
        $this->limit('test_send', 2);
        $owner = User::factory()->create();
        $company = Company::createFor($owner, 'Acme');
        app(CurrentCompany::class)->set($company);
        $contact = Contact::create(['phone' => '+243812345678']);
        app(ConsentService::class)->record($contact, 'paper', 'x', now()->subDay());
        app(CurrentCompany::class)->forget(); // le contexte est ensuite résolu comme en production (session + adhésions)

        $call = fn () => Livewire::actingAs($owner)->test(MessageIndex::class)->set('contactId', $contact->id)->set('body', '')->call('sendTest');
        $call()->assertHasErrors('body');
        $call()->assertHasErrors('body');
        $third = $call();
        $this->assertStringContainsString('Trop de tentatives', $third->errors()->first('body'));
    }

    public function test_admin_actions_are_rate_limited_with_429(): void
    {
        $this->limit('admin_actions', 2);
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $t = Livewire::actingAs($admin)->test(AdminRates::class);
        $t->call('add')->call('add')->assertOk();
        $t->call('add')->assertStatus(429);

        $this->limit('admin_actions', 1);
        Throttle::clear('admin_actions', $admin->id);
        $c = Livewire::actingAs($admin)->test(AdminCompanies::class);
        $c->call('updateSending')->assertOk();
        $c->call('updateSending')->assertStatus(429);
    }

    public function test_global_livewire_ceiling_returns_429(): void
    {
        config(['security.rate_limits.livewire' => ['max' => 3, 'decay' => 60]]);
        $post = fn () => $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => []]);

        for ($i = 0; $i < 3; $i++) {
            $this->assertNotSame(429, $post()->getStatusCode());
        }
        $post()->assertStatus(429);
    }

    public function test_global_livewire_ceiling_is_per_user_not_shared(): void
    {
        config(['security.rate_limits.livewire' => ['max' => 2, 'decay' => 60]]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $post = fn (User $u) => $this->actingAs($u)->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => []])->getStatusCode();

        $post($a);
        $post($a);
        $this->assertSame(429, $post($a));
        $this->assertNotSame(429, $post($b));
    }
}
