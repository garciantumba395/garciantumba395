<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_and_response_is_identical_for_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('sendLink')->assertSet('sent', true);
        Notification::assertSentTo($user, ResetNotification::class);

        Livewire::test(ForgotPassword::class)->set('email', 'inconnu@example.test')->call('sendLink')->assertSet('sent', true);
        Notification::assertCount(1);
    }

    public function test_password_can_be_reset_with_valid_token_only(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => 'mauvais'])->set('email', $user->email)
            ->set('password', 'nouveau-mot-de-passe-1')->set('password_confirmation', 'nouveau-mot-de-passe-1')
            ->call('resetPassword')->assertHasErrors('email');

        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)
            ->set('password', 'nouveau-mot-de-passe-1')->set('password_confirmation', 'nouveau-mot-de-passe-1')
            ->call('resetPassword')->assertRedirect(route('login'));

        $this->assertTrue(\Hash::check('nouveau-mot-de-passe-1', $user->fresh()->password));
    }
}
