<?php

declare(strict_types=1);

namespace App\Domain\Enum\Theme;

enum LabelStyle: string
{
    case Subtle = 'subtle';
    case Plain = 'plain';

    public function cssVariables(): array
    {
        return [
            '--label-subtle-display' => self::Subtle === $this ? 'inline-flex' : 'none',
            '--label-plain-display' => self::Plain === $this ? 'inline-flex' : 'none',
        ];
    }
}
