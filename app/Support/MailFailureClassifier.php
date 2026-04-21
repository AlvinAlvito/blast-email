<?php

namespace App\Support;

class MailFailureClassifier
{
    /**
     * @return array{0: string|null, 1: bool}
     */
    public static function classify(string $message): array
    {
        $normalized = strtolower($message);

        foreach (self::invalidSignals() as $signal) {
            if (str_contains($normalized, $signal)) {
                return ['invalid_email', true];
            }
        }

        foreach (self::recipientBlockedSignals() as $signal) {
            if (str_contains($normalized, $signal)) {
                return ['blocked', false];
            }
        }

        return [null, false];
    }

    public static function isRetryableSenderFailure(?string $message): bool
    {
        if (! is_string($message) || trim($message) === '') {
            return false;
        }

        $normalized = strtolower($message);

        foreach (self::senderFailureSignals() as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    public static function isPermanentContactFailure(?string $message): bool
    {
        if (! is_string($message) || trim($message) === '') {
            return false;
        }

        [$status] = self::classify($message);

        return in_array($status, ['invalid_email', 'blocked'], true);
    }

    /**
     * Failures that clearly point to a bad recipient mailbox/address.
     *
     * @return array<int, string>
     */
    protected static function invalidSignals(): array
    {
        return [
            'recipient address rejected',
            'invalid recipient',
            'bad recipient',
            'user unknown',
            'mailbox unavailable',
            'no such user',
            'unknown user',
            'unknown mailbox',
            'domain not found',
            'invalid address',
            'address rejected',
            'mailbox not found',
        ];
    }

    /**
     * Rare recipient-side hard blocks that should suppress future retries.
     *
     * @return array<int, string>
     */
    protected static function recipientBlockedSignals(): array
    {
        return [
            'recipient blocked',
            'mailbox disabled',
            'account disabled',
        ];
    }

    /**
     * Sender-side / provider-side failures that must never mark the contact bad.
     *
     * @return array<int, string>
     */
    protected static function senderFailureSignals(): array
    {
        return [
            'retry via sender lain',
            'failed to authenticate',
            'too many login attempts',
            'daily user sending limit exceeded',
            'sending limit exceeded',
            'outgoing mail from',
            'has been suspended',
            'authentication',
            'authenticator',
            'quota exceeded',
            'rate limit',
            'try again later',
            'temporary local problem',
            'temporary authentication failure',
            'gsmtp',
        ];
    }
}
