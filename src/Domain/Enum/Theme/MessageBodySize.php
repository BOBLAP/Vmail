<?php

declare(strict_types=1);

namespace App\Domain\Enum\Theme;

/** The message viewport, independently of the reading pane's row spacing. */
enum MessageBodySize: string
{
    case Adaptive = 'adaptive';
    case Compact = 'compact';
    case Cosy = 'cosy';
    case Comfortable = 'comfortable';

    public function height(): ?int
    {
        return match ($this) {
            self::Adaptive => null,
            self::Compact => 80,
            self::Cosy => 200,
            self::Comfortable => 1080,
        };
    }

    public function cssHeight(): string
    {
        return null === $this->height() ? 'auto' : $this->height().'px';
    }
}
