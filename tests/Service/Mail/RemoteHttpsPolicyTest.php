<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Entity\User\User;
use App\Service\Mail\ImageProxySigner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RemoteHttpsPolicyTest extends TestCase
{
    public function testDefaultAndPreferenceRoundTrip(): void
    {
        $user = new User();
        self::assertTrue($user->remoteHttpsOnly);
        $user->remoteHttpsOnly = false;
        self::assertFalse($user->remoteHttpsOnly);
        $user->remoteHttpsOnly = true;
        self::assertTrue($user->remoteHttpsOnly);
    }

    public function testPolicyCannotBeChangedWithoutResigningUrl(): void
    {
        $signer = new ImageProxySigner($this->createStub(UrlGeneratorInterface::class), 'test-secret');
        $url = 'http://images.example/pixel.png';
        self::assertTrue($signer->isValid($url, $signer->sign($url, false), false));
        self::assertFalse($signer->isValid($url, $signer->sign($url), false));
        self::assertFalse($signer->isValid($url, $signer->sign($url, false)));
    }
}
