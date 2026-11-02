<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Domain\DTO\Mail\PostIngestResult;
use App\Domain\Enum\Mail\LabelRole;
use App\Domain\Interface\MailSenderInterface;
use App\Entity\Mail\Account;
use App\Entity\Mail\Message;
use App\Entity\User\User;
use App\Infrastructure\Messaging\Handler\SendAutoResponseHandler;
use App\Infrastructure\Messaging\Message\SendAutoResponseMessage;
use App\Service\Mail\AutoResponder;
use App\Service\Mail\MailSenderRegistry;
use App\Service\Mail\PostIngest\AutoResponseStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class AutoResponseDeliveryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Account $account;
    private array $outgoing = [];
    private bool $acceptDelivery = true;

    protected function setUp(): void
    {
        self::bootKernel();
        $sender = $this->createStub(MailSenderInterface::class);
        $sender->method('supports')->willReturn(true);
        $sender->method('filesSentCopy')->willReturn(true);
        $sender->method('send')->willReturnCallback(function (Email $email, Account $account): bool {
            if (!$this->acceptDelivery) return false;
            $this->outgoing[] = $email;
            return true;
        });
        self::getContainer()->set(MailSenderRegistry::class, new MailSenderRegistry([$sender]));
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $user = new User();
        $user->email = 'auto-' . uniqid() . '@example.test';
        $user->nameFirst = 'Auto'; $user->nameLast = 'Fixture'; $user->roles = ['ROLE_USER']; $user->password = 'x';
        $user->setSetting(User::SETTING_AUTO_RESPONDER, [
            'enabled' => true, 'startsAt' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM), 'endsAt' => (new \DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
            'activatedAt' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM), 'message' => '<p><strong>Absent</strong></p>', 'format' => 'html', 'subject' => 'Away', 'campaign' => str_repeat('b', 32),
        ]);
        $this->em->persist($user);
        $account = new Account();
        $account->email = 'receiver@example.test'; $account->username = 'receiver@example.test';
        $account->imapHost = 'localhost'; $account->imapPort = 993; $account->imapEncryption = 'ssl';
        $account->smtpHost = 'localhost'; $account->smtpPort = 587; $account->smtpEncryption = 'starttls';
        $account->password = 'x'; $account->authType = 'password'; $account->isActive = true;
        $user->addAccount($account);
        $this->em->persist($account);
        $this->em->flush();
        $this->account = $account;
    }

    protected function tearDown(): void
    {
        if (isset($this->em) && $this->em->getConnection()->isTransactionActive()) $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function source(string $sender = 'human@example.test'): Message
    {
        $message = new Message();
        $message->account = $this->account; $message->fromAddress = $sender;
        $message->toAddresses = [['address' => $this->account->email, 'name' => '']];
        $message->subject = 'Question'; $message->messageId = uniqid() . '@example.test';
        $message->hasAttachments = false;
        $message->bodyText = 'Hello'; $message->receivedAt = new \DateTimeImmutable('-10 seconds');
        $message->providerAcceptedAt = $message->receivedAt;
        $this->em->persist($message); $this->em->flush();
        return $message;
    }

    private function job(Message $source): SendAutoResponseMessage { return new SendAutoResponseMessage($source->id, str_repeat('b', 32)); }

    public function testOneReplySurvivesReplayAndFurtherMessagesFromTheSameSender(): void
    {
        $source = $this->source();
        $handler = self::getContainer()->get(SendAutoResponseHandler::class);
        $handler($this->job($source));
        $handler($this->job($source));
        $handler($this->job($this->source()));
        self::assertCount(1, $this->outgoing);
        self::assertSame('auto-replied', $this->outgoing[0]->getHeaders()->get('Auto-Submitted')->getBodyAsString());
        self::assertSame('All', $this->outgoing[0]->getHeaders()->get('X-Auto-Response-Suppress')->getBodyAsString());
        self::assertStringContainsString($source->messageId, $this->outgoing[0]->getHeaders()->get('In-Reply-To')->getBodyAsString());
        self::assertStringContainsString('<strong>Absent</strong>', $this->outgoing[0]->getHtmlBody());
        self::assertStringContainsString('Absent', $this->outgoing[0]->getTextBody());
        $replyId = $this->em->getConnection()->fetchOne('SELECT reply_id FROM auto_response_delivery WHERE usr_id = ?', [$this->account->usr->id]);
        $reply = $this->em->find(Message::class, $replyId);
        self::assertNotNull($reply->sentAt);
        self::assertTrue($reply->labels->exists(static fn ($key, $label): bool => LabelRole::Sent === $label->role));
    }

    public function testStoppingBeforeTheQueuedJobRunsPreventsSending(): void
    {
        $source = $this->source();
        $config = (new AutoResponder())->configuration($this->account->usr);
        $config['enabled'] = false;
        $this->account->usr->setSetting(User::SETTING_AUTO_RESPONDER, $config); $this->em->flush();
        self::getContainer()->get(SendAutoResponseHandler::class)($this->job($source));
        self::assertCount(0, $this->outgoing);
    }

    public function testPlainTextChoiceProducesNoHtmlPart(): void
    {
        $config = (new AutoResponder())->configuration($this->account->usr);
        $config['format'] = 'text';
        $config['message'] = "Absent\nRetour lundi. <texte littéral>";
        $this->account->usr->setSetting(User::SETTING_AUTO_RESPONDER, $config);
        $this->em->flush();
        self::getContainer()->get(SendAutoResponseHandler::class)($this->job($this->source()));
        self::assertCount(1, $this->outgoing);
        self::assertSame($config['message'], $this->outgoing[0]->getTextBody());
        self::assertNull($this->outgoing[0]->getHtmlBody());
    }

    public function testRetryReusesTheSameReplyAfterTransportRefusal(): void
    {
        $job = $this->job($this->source());
        $userId = $this->account->usr->id;
        $handler = self::getContainer()->get(SendAutoResponseHandler::class);
        $this->acceptDelivery = false;
        try {
            $handler($job);
            self::fail('A refused delivery must request a queue retry.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('transport refused', $error->getMessage());
        }
        $replyId = $this->em->getConnection()->fetchOne('SELECT reply_id FROM auto_response_delivery WHERE usr_id = ?', [$userId]);
        self::assertNotFalse($replyId);
        $this->acceptDelivery = true;
        $handler($job);
        self::assertCount(1, $this->outgoing);
        self::assertSame($replyId, $this->em->getConnection()->fetchOne('SELECT reply_id FROM auto_response_delivery WHERE usr_id = ?', [$userId]));
        self::assertNotNull($this->em->find(Message::class, $replyId)->sentAt);
    }

    public function testDeletingTheSentCopyDoesNotPermitAnotherReply(): void
    {
        $handler = self::getContainer()->get(SendAutoResponseHandler::class);
        $handler($this->job($this->source()));
        $replyId = $this->em->getConnection()->fetchOne('SELECT reply_id FROM auto_response_delivery WHERE usr_id = ?', [$this->account->usr->id]);
        $this->em->remove($this->em->find(Message::class, $replyId));
        $this->em->flush();
        $handler($this->job($this->source()));
        self::assertCount(1, $this->outgoing);
    }

    public function testChangingThePeriodInvalidatesAnAlreadyQueuedJob(): void
    {
        $job = $this->job($this->source());
        $config = (new AutoResponder())->configuration($this->account->usr);
        $config['campaign'] = str_repeat('c', 32);
        $this->account->usr->setSetting(User::SETTING_AUTO_RESPONDER, $config);
        $this->em->flush();
        self::getContainer()->get(SendAutoResponseHandler::class)($job);
        self::assertCount(0, $this->outgoing);
    }

    public function testHistoricalImportsQueueNothingAndLiveMailUsesExport(): void
    {
        $source = $this->source();
        $step = self::getContainer()->get(AutoResponseStep::class);
        $transport = self::getContainer()->get('messenger.transport.export');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $count = count($transport->getSent());
        $step->afterCommit(new PostIngestResult([$source], [], [], false));
        self::assertCount($count, $transport->getSent());
        $step->afterCommit(new PostIngestResult([$source], [], [], true));
        self::assertCount($count + 1, $transport->getSent());
        self::assertInstanceOf(SendAutoResponseMessage::class, $transport->getSent()[$count]->getMessage());
    }
}
