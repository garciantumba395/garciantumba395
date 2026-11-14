<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\AccountService;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_admin_sets_the_flag_and_audits(): void
    {
        $user = User::factory()->create();

        $this->artisan('platform:grant-admin', ['email' => $user->email])->expectsOutputToContain('attribué')->assertExitCode(0);

        $this->assertTrue($user->fresh()->is_platform_admin);
        $log = AuditLog::where('action', 'platform_admin.granted')->firstOrFail();
        $this->assertNull($log->actor_id); // console : pas d'utilisateur connecté
        $this->assertSame($user->id, $log->subject_id);
        $this->assertStringNotContainsString($user->email, json_encode($log->toArray()));
    }

    public function test_grant_is_idempotent_and_unknown_email_fails(): void
    {
        $user = User::factory()->create();
        $this->artisan('platform:grant-admin', ['email' => $user->email])->assertExitCode(0);
        $this->artisan('platform:grant-admin', ['email' => $user->email])->expectsOutputToContain('déjà')->assertExitCode(0);
        $this->assertSame(1, AuditLog::where('action', 'platform_admin.granted')->count());

        $this->artisan('platform:grant-admin', ['email' => 'inconnu@example.test'])->assertExitCode(1);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_grant_refuses_a_disabled_account(): void
    {
        $admin = User::factory()->create();
        app(AccountService::class)->grantPlatformAdmin($admin);
        $user = User::factory()->create();
        $user->forceFill(['disabled_at' => now()])->save();

        $this->artisan('platform:grant-admin', ['email' => $user->email])->assertExitCode(1);
        $this->assertFalse((bool) $user->fresh()->is_platform_admin);
    }

    public function test_revoke_admin_works_when_another_admin_remains(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->artisan('platform:grant-admin', ['email' => $a->email]);
        $this->artisan('platform:grant-admin', ['email' => $b->email]);

        $this->artisan('platform:revoke-admin', ['email' => $a->email])->expectsOutputToContain('retiré')->assertExitCode(0);

        $this->assertFalse($a->fresh()->is_platform_admin);
        $this->assertSame($a->id, AuditLog::where('action', 'platform_admin.revoked')->firstOrFail()->subject_id);
    }

    public function test_revoke_refuses_to_remove_the_last_admin(): void
    {
        $a = User::factory()->create();
        $this->artisan('platform:grant-admin', ['email' => $a->email]);

        $this->artisan('platform:revoke-admin', ['email' => $a->email])->expectsOutputToContain('dernier administrateur')->assertExitCode(1);

        $this->assertTrue($a->fresh()->is_platform_admin);
        $this->assertSame(0, AuditLog::where('action', 'platform_admin.revoked')->count());
    }

    public function test_last_admin_rule_counts_only_active_admins(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->artisan('platform:grant-admin', ['email' => $a->email]);
        $this->artisan('platform:grant-admin', ['email' => $b->email]);
        $b->forceFill(['disabled_at' => now()])->save(); // admin désactivé : ne compte pas

        $this->artisan('platform:revoke-admin', ['email' => $a->email])->assertExitCode(1);
        $this->assertTrue($a->fresh()->is_platform_admin);
    }

    public function test_revoke_on_non_admin_and_unknown_email(): void
    {
        $u = User::factory()->create();
        $this->artisan('platform:revoke-admin', ['email' => $u->email])->expectsOutputToContain('rien à faire')->assertExitCode(0);
        $this->artisan('platform:revoke-admin', ['email' => 'inconnu@example.test'])->assertExitCode(1);
    }

    public function test_there_is_no_web_route_to_grant_admin(): void
    {
        $paths = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
        $this->assertStringNotContainsString('grant', $paths);
        $this->assertStringNotContainsString('platform', $paths);
    }
}
