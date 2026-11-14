<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class ContactGroup extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['name'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_contact')->withTimestamps();
    }

    /**
     * Ajoute des contacts au groupe. Seuls les contacts de la même entreprise sont retenus
     * (requête filtrée par le scope) et la table pivot porte company_id (clé étrangère composite).
     *
     * @param  array<int>  $contactIds
     */
    public function addContacts(array $contactIds): int
    {
        $valid = Contact::whereIn('id', $contactIds)->pluck('id');
        $rows = $valid->map(fn ($id) => [
            'company_id' => $this->company_id,
            'contact_id' => $id,
            'contact_group_id' => $this->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        return $rows ? DB::table('contact_group_contact')->insertOrIgnore($rows) : 0;
    }
}
