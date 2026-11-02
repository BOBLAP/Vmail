<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Entity\Mail\Account;
use App\Entity\Mail\Message;
use App\Entity\User\User;
use App\Service\Mail\AutoResponder;
use App\Service\Mail\AutoResponsePolicy;
use App\Service\Mail\HeaderNormalizer;
use App\Service\Mail\MailBodySanitizer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AutoResponderTest extends KernelTestCase
{
    private function user(): User
    {
        $user = new User();
        $user->setSetting(User::SETTING_AUTO_RESPONDER, [
            'enabled' => true, 'startsAt' => '2026-11-02T10:00:00+00:00', 'endsAt' => '2026-11-02T12:00:00+00:00',
            'activatedAt' => '2026-11-02T09:00:00+00:00', 'message' => 'Away', 'subject' => '', 'format' => 'text', 'campaign' => str_repeat('a', 32),
        ]);
        return $user;
    }

    private function source(): Message
    {
        $account = new Account();
        $account->email = 'me@example.com';
        $account->isActive = true;
        $this->user()->addAccount($account);
        $source = new Message();
        $source->account = $account;
        $source->fromAddress = 'sender@example.com';
        $source->providerAcceptedAt = new DateTimeImmutable('2026-11-02T10:30:00Z');
        return $source;
    }

    public function testTheWindowIsInclusiveAtStartAndExclusiveAtEnd(): void
    {
        $responder = new AutoResponder();
        self::assertFalse($responder->active($this->user(), new DateTimeImmutable('2026-11-02T09:59:59Z')));
        self::assertTrue($responder->active($this->user(), new DateTimeImmutable('2026-11-02T10:00:00Z')));
        self::assertFalse($responder->active($this->user(), new DateTimeImmutable('2026-11-02T12:00:00Z')));
        self::assertFalse($responder->active(new User()));
    }

    public function testSavingConvertsTimesToUtcAndSanitizesHtml(): void
    {
        self::bootKernel();
        $user = new User();
        $responder = new AutoResponder();
        $data = ['enabled' => true, 'startsAt' => new DateTimeImmutable('2026-11-02T11:00:37+01:00'), 'endsAt' => new DateTimeImmutable('2026-11-02T13:00:42+01:00'), 'format' => 'html', 'subject' => 'Away', 'message' => '<p><strong>Absent</strong></p><script>alert(1)</script>'];
        $responder->store($user, $data, self::getContainer()->get(MailBodySanitizer::class), new DateTimeImmutable('2026-11-02T10:00:00Z'));
        $stored = $responder->configuration($user);
        self::assertSame('2026-11-02T10:00:37+00:00', $stored['startsAt']);
        self::assertSame('2026-11-02T12:00:42+00:00', $stored['endsAt']);
        self::assertStringNotContainsString('<script', $stored['message']);
        self::assertStringContainsString('<strong>Absent</strong>', $stored['message']);
        $data['message'] = '<p>Updated</p>';
        $responder->store($user, $data, self::getContainer()->get(MailBodySanitizer::class), new DateTimeImmutable('2026-11-02T10:10:00Z'));
        self::assertSame($stored['campaign'], $responder->configuration($user)['campaign']);
    }

    public function testReversedPeriodIsRefused(): void
    {
        self::bootKernel();
        $this->expectException(\InvalidArgumentException::class);
        (new AutoResponder())->store(new User(), ['enabled' => true, 'startsAt' => new DateTimeImmutable('+2 days'), 'endsAt' => new DateTimeImmutable('+1 day')], self::getContainer()->get(MailBodySanitizer::class));
    }

    public function testAnOrdinaryRecentMessageIsEligible(): void
    {
        $policy = new AutoResponsePolicy(new AutoResponder(), new HeaderNormalizer());
        self::assertSame('sender@example.com', $policy->recipient($this->source(), new DateTimeImmutable('2026-11-02T11:00:00Z')));
    }

    #[DataProvider('automaticHeaders')]
    public function testAutomaticAndBulkMessagesAreRefused(array $headers): void
    {
        $source = $this->source();
        $source->headers = $headers;
        self::assertNull((new AutoResponsePolicy(new AutoResponder(), new HeaderNormalizer()))->recipient($source, new DateTimeImmutable('2026-11-02T11:00:00Z')));
    }

    public static function automaticHeaders(): iterable
    {
        yield [['Auto-Submitted' => 'auto-replied']];
        yield [['auto_submitted' => 'auto-generated']];
        yield [['Auto-Submitted' => ['no', 'auto-replied']]];
        yield [['List-Id' => '<list.example.com>']];
        yield [['Precedence' => 'bulk']];
        yield [['Return-Path' => '<>']];
        yield [['X-Auto-Response-Suppress' => 'All']];
        yield [['Content-Type' => 'multipart/report; report-type=delivery-status']];
        yield [['X-Spam-Status' => 'Yes, score=10']];
    }

    public function testHistoryAndOwnMailAreNotAnswered(): void
    {
        $policy = new AutoResponsePolicy(new AutoResponder(), new HeaderNormalizer());
        $source = $this->source();
        $source->providerAcceptedAt = new DateTimeImmutable('2026-11-01T10:30:00Z');
        self::assertNull($policy->recipient($source, new DateTimeImmutable('2026-11-02T11:00:00Z')));
        $source = $this->source();
        $source->fromAddress = 'me@example.com';
        self::assertNull($policy->recipient($source, new DateTimeImmutable('2026-11-02T11:00:00Z')));
    }
}
