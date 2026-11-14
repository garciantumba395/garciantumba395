<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Support\TenantProbe;
use Tests\TestCase;

/**
 * Gabarit des tests d'isolation : tout nouveau modèle métier doit avoir
 * l'équivalent de ces tests (lecture, écriture, accès par ID, absence de contexte).
 */
class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('tenant_probes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('label');
            $t->timestamps();
        });
        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
    }

    private function as(Company $c): void
    {
        app(CurrentCompany::class)->set($c);
    }

    public function test_queries_only_return_active_company_rows(): void
    {
        $this->as($this->a);
        TenantProbe::create(['label' => 'a1']);
        $this->as($this->b);
        TenantProbe::create(['label' => 'b1']);

        $this->as($this->a);
        $this->assertSame(['a1'], TenantProbe::pluck('label')->all());
        $this->as($this->b);
        $this->assertSame(['b1'], TenantProbe::pluck('label')->all());
    }

    public function test_cannot_find_update_or_delete_other_company_row_by_id(): void
    {
        $this->as($this->b);
        $foreign = TenantProbe::create(['label' => 'secret']);

        $this->as($this->a);
        $this->assertNull(TenantProbe::find($foreign->id));
        $this->assertSame(0, TenantProbe::whereKey($foreign->id)->update(['label' => 'piraté']));
        $this->assertSame(0, TenantProbe::whereKey($foreign->id)->delete());

        $this->as($this->b);
        $this->assertSame('secret', TenantProbe::find($foreign->id)->label);
    }

    public function test_company_id_is_filled_from_context_on_create(): void
    {
        $this->as($this->a);
        $this->assertSame($this->a->id, TenantProbe::create(['label' => 'x'])->company_id);
    }

    public function test_no_active_company_means_no_rows_and_no_creation(): void
    {
        $this->as($this->a);
        TenantProbe::create(['label' => 'x']);

        app(CurrentCompany::class)->forget();
        $this->assertSame(0, TenantProbe::count());

        $this->expectException(LogicException::class);
        TenantProbe::create(['label' => 'orphelin']);
    }

    public function test_scope_can_only_be_bypassed_explicitly(): void
    {
        $this->as($this->a);
        TenantProbe::create(['label' => 'a']);
        $this->as($this->b);
        TenantProbe::create(['label' => 'b']);

        $this->assertSame(2, TenantProbe::withoutGlobalScope(CompanyScope::class)->count());
    }

    public function test_authenticated_context_comes_from_memberships_not_from_forged_session(): void
    {
        $user = User::factory()->create();
        $this->a->users()->attach($user, ['role' => 'member']);

        // Session forgée pointant vers une entreprise dont l'utilisateur n'est pas membre.
        $this->actingAs($user)->withSession([CurrentCompany::SESSION_KEY => $this->b->id]);
        app(CurrentCompany::class)->forget();

        $this->assertSame($this->a->id, app(CurrentCompany::class)->id());
    }
}
