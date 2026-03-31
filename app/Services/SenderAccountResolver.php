<?php

namespace App\Services;

use App\Models\SenderAccount;
use Illuminate\Support\Carbon;

class SenderAccountResolver
{
    public function next(): ?SenderAccount
    {
        $now = Carbon::now();

        $sender = SenderAccount::query()
            ->where('is_active', true)
            ->orderBy('last_sent_at')
            ->orderBy('id')
            ->get()
            ->first(fn (SenderAccount $senderAccount) => $senderAccount->canSendNow($now));

        if (! $sender) {
            return null;
        }

        if ($sender->syncQuotaSnapshot($now)) {
            $sender->save();
        }

        return $sender;
    }

    public function hasActiveSender(): bool
    {
        return SenderAccount::query()
            ->where('is_active', true)
            ->exists();
    }

    public function nextAvailableAt(): ?Carbon
    {
        $now = Carbon::now();

        return SenderAccount::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (SenderAccount $senderAccount) => $senderAccount->nextAvailableAt($now))
            ->filter()
            ->sort()
            ->first();
    }
}
