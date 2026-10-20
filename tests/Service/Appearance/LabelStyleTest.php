<?php

declare(strict_types=1);

namespace App\Tests\Service\Appearance;

use App\Domain\Enum\Theme\LabelStyle;
use App\Entity\Embeddable\Appearance;
use App\Jmap\Mapper\AppearanceMapper;
use App\Service\Appearance\AppearanceRenderer;
use App\Service\Appearance\BackgroundResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LabelStyleTest extends TestCase
{
    public function testSizingSurvivesExportAndFeedsBothTheRendererAndJmap(): void
    {
        $renderer = new AppearanceRenderer(new BackgroundResolver(
            $this->createStub(Packages::class),
            $this->createStub(UrlGeneratorInterface::class),
        ));
        foreach (LabelStyle::cases() as $size) {
            $appearance = new Appearance()->applyArray(['labelStyle' => $size->value]);
            $restored = new Appearance()->applyArray($appearance->toArray());
            self::assertSame($size, $restored->labelStyle);
            self::assertStringContainsString('--label-plain-display:'.$size->cssVariables()['--label-plain-display'], $renderer->cssVariables($restored));
            self::assertSame($size->value, new AppearanceMapper()->toJmap($restored)['labelStyle']);
        }
    }

    public function testExistingUsersDefaultToSubtleAndInvalidInputKeepsTheirChoice(): void
    {
        $appearance = new Appearance();
        self::assertSame(LabelStyle::Subtle, $appearance->labelStyle);
        $appearance->applyArray(['labelStyle' => 'plain']);
        $appearance->applyArray(['labelStyle' => 'unknown']);
        self::assertSame(LabelStyle::Plain, $appearance->labelStyle);
    }
}
