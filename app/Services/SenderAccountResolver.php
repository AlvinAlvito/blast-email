<?php

namespace App\Services;

use App\Models\SenderAccount;

class SenderAccountResolver
{
    public function next(): ?SenderAccount
    {
        return SenderAccount::query()
            ->where('is_active', true)
            ->whereColumn('sent_today', '<', 'daily_limit')
            ->orderBy('last_sent_at')
            ->orderBy('id')
            ->first();
    }
}
