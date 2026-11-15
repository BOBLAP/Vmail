<?php

declare(strict_types=1);

namespace App\Domain\Enum\Mail;

use App\Entity\Mail\Account;
use App\Entity\User\User;

enum SyncFrequency: string
{
    case Default = 'default';
    case Priority = 'priority';
    case Secondary = 'secondary';
    case Economy = 'economy';

    public const string ACCOUNT_SETTING = 'sync.frequency_group';
    public const string USER_SETTING = 'mail.sync_intervals';
    public const int MIN_MINUTES = 1;
    public const int MAX_MINUTES = 1440;

    public static function forAccount(Account $account): self
    {
        $stored = $account->getSetting(self::ACCOUNT_SETTING);

        return (is_string($stored) ? self::tryFrom($stored) : null) ?? self::Default;
    }

    public function minutes(?User $user): int
    {
        $intervals = $user?->getSetting(self::USER_SETTING);
        $value = is_array($intervals) ? ($intervals[$this->value] ?? null) : null;
        if (is_int($value) && $value >= self::MIN_MINUTES && $value <= self::MAX_MINUTES) {
            return $value;
        }

        return match ($this) {
            self::Default => 15,
            self::Priority => 5,
            self::Secondary => 30,
            self::Economy => 60,
        };
    }
}
