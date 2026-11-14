<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\AuditLogger;
use App\Livewire\Admin\AuditLogs;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AuditViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Admin Plateforme']);
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->owner = User::factory()->create(['name' => 'Proprio Alpha']);
        $this->a = Company::createFor($this->owner, 'Alpha SARL');
        $this->b = Company::createFor(User::factory()->create(), 'Beta SARL');
    }

    private function log(string $action, ?Company $co, ?User $actor, ?string $date = null): AuditLog
    {
        $log = app(AuditLogger::class)->recordAs($actor?->id, $action, $co);
        if ($date) {
            AuditLog::query()->whereKey($log->id)->toBase()->update(['created_at' => Carbon::parse($date, config('app.timezone'))]);
        }

        return $log;
    }

    private function seedLogs(): void
    {
        $this->log('credits.added', $this->a, $this->admin, '2026-03-10 09:00:00');
        $this->log('campaign.paused', $this->a, null, '2026-03-12 23:30:00');
        $this->log('rates.created', null, $this->admin, '2026-03-15 00:10:00');
        $this->log('company.updated', $this->b, $this->owner, '2026-03-20 12:00:00');
    }

    private function actions(Testable $page): array
    {
        return $page->viewData('logs')->pluck('action')->all();
    }

    public function test_lists_newest_first_without_filter(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class);
        $this->assertSame(['company.updated', 'rates.created', 'campaign.paused', 'credits.added'], $this->actions($page));
        $page->assertSee('Système')->assertSee('Alpha SARL');
    }

    public function test_filter_by_action(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class)->set('action', 'credits.added');
        $this->assertSame(['credits.added'], $this->actions($page));
    }

    public function test_filter_by_company(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class)->set('companyId', (string) $this->a->id);
        $this->assertSame(['campaign.paused', 'credits.added'], $this->actions($page));
        $page->set('companyId', (string) $this->b->id);
        $this->assertSame(['company.updated'], $this->actions($page));
    }

    public function test_filter_by_actor_and_system(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class)->set('actorId', (string) $this->admin->id);
        $this->assertSame(['rates.created', 'credits.added'], $this->actions($page));
        $page->set('actorId', 'system');
        $this->assertSame(['campaign.paused'], $this->actions($page));
        $page->set('actorId', (string) $this->owner->id);
        $this->assertSame(['company.updated'], $this->actions($page));
    }

    public function test_filter_by_period_uses_the_application_timezone_with_inclusive_bounds(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class)->set('dateFrom', '2026-03-12')->set('dateTo', '2026-03-15');
        // 12/03 23:30 et 15/03 00:10 sont inclus (bornes de jour dans le fuseau de l'application).
        $this->assertSame(['rates.created', 'campaign.paused'], $this->actions($page));

        $page->set('dateFrom', '2026-03-13')->set('dateTo', '');
        $this->assertSame(['company.updated', 'rates.created'], $this->actions($page));
        $page->set('dateFrom', '')->set('dateTo', '2026-03-10');
        $this->assertSame(['credits.added'], $this->actions($page));
    }

    public function test_filters_combine_and_invalid_input_is_ignored_safely(): void
    {
        $this->seedLogs();
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class)->set('companyId', (string) $this->a->id)->set('actorId', (string) $this->admin->id);
        $this->assertSame(['credits.added'], $this->actions($page));

        $page->set('companyId', '')->set('actorId', '')->set('dateFrom', 'pas-une-date')->assertHasErrors('dateFrom');
        $this->assertCount(4, $page->viewData('logs'));
        $page->set('companyId', '1 OR 1=1')->set('actorId', 'abc');
        $this->assertCount(4, $page->viewData('logs'));
    }

    public function test_is_paginated(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->log('credits.added', $this->a, $this->admin);
        }
        $page = Livewire::actingAs($this->admin)->test(AuditLogs::class);
        $this->assertCount(25, $page->viewData('logs'));
        $this->assertSame(30, $page->viewData('logs')->total());
        $page->call('gotoPage', 2);
        $this->assertCount(5, $page->viewData('logs'));
    }

    public function test_viewer_is_read_only(): void
    {
        $this->assertSame([], array_values(array_filter(get_class_methods(AuditLogs::class), fn ($m) => in_array($m, ['delete', 'destroy', 'remove', 'update', 'edit', 'save', 'clear', 'purge']))));
    }

    public function test_company_sheet_shows_only_that_companys_recent_activity(): void
    {
        $this->seedLogs();
        $this->actingAs($this->admin)->get('/admin/entreprises/'.$this->a->id)->assertOk()
            ->assertSee('credits.added')->assertSee('campaign.paused')->assertDontSee('company.updated');
    }
}
