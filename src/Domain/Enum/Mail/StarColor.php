<?php

declare(strict_types=1);

namespace App\Domain\Enum\Mail;

final class StarColor
{
    public static function next(bool $starred, int $color): int
    {
        return !$starred ? 1 : (max(1, min(7, $color)) + 1) % 8;
    }
}
