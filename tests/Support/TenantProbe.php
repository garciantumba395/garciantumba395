<?php

namespace Tests\Support;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Modèle factice pour tester le trait BelongsToCompany (table créée dans les tests). */
class TenantProbe extends Model
{
    use BelongsToCompany;

    protected $table = 'tenant_probes';

    protected $guarded = [];
}
