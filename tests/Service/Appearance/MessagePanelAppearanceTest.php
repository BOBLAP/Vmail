<?php

declare(strict_types=1);

namespace App\Tests\Service\Appearance;

use App\Domain\Enum\Theme\Density;
use App\Entity\Embeddable\Appearance;
use App\Jmap\Mapper\AppearanceMapper;
use App\Service\Appearance\AppearanceRenderer;
use App\Service\Appearance\BackgroundResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MessagePanelAppearanceTest extends TestCase
{
    public function testPreferencesSurviveExportAndReachRendererAndJmap(): void
    {
        $appearance = new Appearance()->applyArray([
            'listDensity' => 'neat', 'invertMessagePanel' => '1', 'messagePanelContrast' => 100,
        ]);
        $restored = new Appearance()->applyArray($appearance->toArray());
        self::assertSame(Density::Neat, $restored->listDensity);
        self::assertTrue($restored->invertMessagePanel);
        $renderer = new AppearanceRenderer(new BackgroundResolver(
            $this->createStub(Packages::class), $this->createStub(UrlGeneratorInterface::class),
        ));
        self::assertStringContainsString('list-neat', $renderer->htmlClass($restored));
        self::assertStringContainsString('invert-message-panel', $renderer->htmlClass($restored));
        self::assertStringContainsString('--message-panel-grey:255 255 255', $renderer->cssVariables($restored));
        $mapped = new AppearanceMapper()->toJmap($restored);
        self::assertSame('neat', $mapped['listDensity']);
        self::assertTrue($mapped['invertMessagePanel']);
        self::assertSame(100, $mapped['messagePanelContrast']);
    }

    public function testContrastBoundsAndDefault(): void
    {
        $appearance = new Appearance();
        self::assertFalse($appearance->invertMessagePanel);
        self::assertSame(70, $appearance->messagePanelContrast);
        foreach ([-50 => 128, 0 => 128, 50 => 192, 100 => 255, 150 => 255] as $contrast => $grey) {
            $appearance->applyArray(['messagePanelContrast' => $contrast]);
            self::assertSame($grey, $appearance->messagePanelGrey());
        }
        $appearance->applyArray(['invertMessagePanel' => '0']);
        self::assertFalse($appearance->invertMessagePanel);
    }

    public function testOpacityBoundsExportRendererAndJmap(): void
    {
        $appearance = new Appearance();
        self::assertSame(100, $appearance->messagePanelOpacity);
        $appearance->applyArray(['messagePanelOpacity' => -10]);
        self::assertSame(0, $appearance->messagePanelOpacity);
        $appearance->messagePanelOpacity = 150;
        self::assertSame(100, $appearance->messagePanelOpacity);
        $appearance->applyArray(['messagePanelOpacity' => '35']);
        $restored = new Appearance()->applyArray($appearance->toArray());
        self::assertSame(35, $restored->messagePanelOpacity);
        $renderer = new AppearanceRenderer(new BackgroundResolver(
            $this->createStub(Packages::class), $this->createStub(UrlGeneratorInterface::class),
        ));
        self::assertStringContainsString('--message-panel-opacity:35%', $renderer->cssVariables($restored));
        self::assertSame(35, new AppearanceMapper()->toJmap($restored)['messagePanelOpacity']);
    }

    public function testNeatCannotChangeOtherSurfaces(): void
    {
        $appearance = new Appearance()->applyArray([
            'density' => 'neat', 'sidebarDensity' => 'neat', 'readingDensity' => 'neat', 'listDensity' => 'neat',
        ]);
        self::assertSame(Density::Comfortable, $appearance->density);
        self::assertNull($appearance->sidebarDensity);
        self::assertNull($appearance->readingDensity);
        self::assertSame(Density::Neat, $appearance->listDensity);
    }
}
