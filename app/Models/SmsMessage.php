<?php

namespace App\Models;

use App\Domain\Sms\SmsStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'simulated' => 'boolean',
            'sending_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
