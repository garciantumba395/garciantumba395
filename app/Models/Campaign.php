<?php

namespace App\Models;

use App\Domain\Campaigns\CampaignStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToCompany;

    // status, compteurs et pause : modifiés uniquement par CampaignService / jobs (jamais assignables en masse).
    protected $fillable = ['name', 'body', 'contact_group_id', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime', 'paused_at' => 'datetime', 'started_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'draft'];

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }
}
