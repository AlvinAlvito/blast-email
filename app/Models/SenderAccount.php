<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenderAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'reply_to_address',
        'daily_limit',
        'hourly_limit',
        'sent_today',
        'last_sent_at',
        'is_active',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
