<?php

declare(strict_types=1);

namespace App\Tests\Service\Appearance;

use App\Domain\Enum\Theme\MessageBodySize;
use App\Entity\Embeddable\Appearance;
use App\Jmap\Mapper\AppearanceMapper;
use App\Service\Appearance\AppearanceRenderer;
use App\Service\Appearance\BackgroundResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MessageBodySizeTest extends TestCase
{
    public function testSizingSurvivesExportAndFeedsBothTheRendererAndJmap(): void
    {
        $renderer = new AppearanceRenderer(new BackgroundResolver(
            $this->createStub(Packages::class),
            $this->createStub(UrlGeneratorInterface::class),
        ));
        foreach (MessageBodySize::cases() as $size) {
            $appearance = new Appearance()->applyArray(['messageBodySize' => $size->value]);
            $restored = new Appearance()->applyArray($appearance->toArray());
            self::assertSame($size, $restored->messageBodySize);
            self::assertStringContainsString('--message-body-height:'.$size->cssHeight(), $renderer->cssVariables($restored));
            self::assertSame($size->value, new AppearanceMapper()->toJmap($restored)['messageBodySize']);
        }
    }

    public function testExistingUsersDefaultToAdaptiveAndInvalidInputKeepsTheirChoice(): void
    {
        $appearance = new Appearance();
        self::assertSame(MessageBodySize::Adaptive, $appearance->messageBodySize);
        $appearance->applyArray(['messageBodySize' => 'cosy']);
        $appearance->applyArray(['messageBodySize' => 'unknown']);
        self::assertSame(MessageBodySize::Cosy, $appearance->messageBodySize);
    }
}
