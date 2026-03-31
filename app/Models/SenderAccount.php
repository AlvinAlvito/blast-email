<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function effectiveSentToday(?Carbon $now = null): int
    {
        $now ??= now();

        if (! $this->last_sent_at || ! $this->last_sent_at->isSameDay($now)) {
            return 0;
        }

        return $this->campaignRecipients()
            ->where('status', 'sent')
            ->where('sent_at', '>=', $now->copy()->startOfDay())
            ->count();
    }

    public function effectiveSentThisHour(?Carbon $now = null): int
    {
        $now ??= now();

        if (! $this->last_sent_at || $this->last_sent_at->lt($now->copy()->startOfHour())) {
            return 0;
        }

        return $this->campaignRecipients()
            ->where('status', 'sent')
            ->where('sent_at', '>=', $now->copy()->startOfHour())
            ->count();
    }

    public function remainingDailyQuota(?Carbon $now = null): int
    {
        return max(0, $this->daily_limit - $this->effectiveSentToday($now));
    }

    public function remainingHourlyQuota(?Carbon $now = null): int
    {
        return max(0, $this->hourly_limit - $this->effectiveSentThisHour($now));
    }

    public function canSendNow(?Carbon $now = null): bool
    {
        return $this->remainingDailyQuota($now) > 0 && $this->remainingHourlyQuota($now) > 0;
    }

    public function nextAvailableAt(?Carbon $now = null): ?Carbon
    {
        $now ??= now();

        if ($this->canSendNow($now)) {
            return $now;
        }

        if ($this->remainingDailyQuota($now) <= 0) {
            return $now->copy()->addDay()->startOfDay();
        }

        if ($this->remainingHourlyQuota($now) <= 0) {
            return $now->copy()->addHour()->startOfHour();
        }

        return null;
    }

    public function syncQuotaSnapshot(?Carbon $now = null): bool
    {
        $now ??= now();
        $effectiveSentToday = $this->effectiveSentToday($now);
        $dirty = false;

        if ($this->sent_today !== $effectiveSentToday) {
            $this->sent_today = $effectiveSentToday;
            $dirty = true;
        }

        if ($effectiveSentToday === 0 && $this->last_sent_at && ! $this->last_sent_at->isSameDay($now)) {
            $this->last_sent_at = null;
            $dirty = true;
        }

        return $dirty;
    }
}
