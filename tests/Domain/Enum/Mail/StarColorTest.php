<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum\Mail;

use App\Domain\Enum\Mail\StarColor;
use PHPUnit\Framework\TestCase;

final class StarColorTest extends TestCase
{
    public function testAppearanceDefaultAndExport(): void
    {
        $appearance = new \App\Entity\Embeddable\Appearance();
        self::assertTrue($appearance->starColors);
        $appearance->applyArray(['starColors' => '0']);
        self::assertFalse($appearance->starColors);
        $restored = new \App\Entity\Embeddable\Appearance()->applyArray($appearance->toArray());
        self::assertFalse($restored->starColors);
        self::assertFalse(new \App\Jmap\Mapper\AppearanceMapper()->toJmap($restored)['starColors']);
    }

    public function testFullCycleAndExistingStars(): void
    {
        self::assertSame(1, StarColor::next(false, 7));
        self::assertSame(2, StarColor::next(true, 0));
        for ($color = 1; $color < 7; ++$color) {
            self::assertSame($color + 1, StarColor::next(true, $color));
        }
        self::assertSame(0, StarColor::next(true, 7));
    }
}
