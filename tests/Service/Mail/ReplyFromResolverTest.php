<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Domain\Enum\Account\EmailAliasSource;
use App\Domain\Enum\Account\EmailAliasStatus;
use App\Entity\Mail\Account;
use App\Entity\Mail\EmailAlias;
use App\Entity\Mail\Message;
use App\Service\Mail\ReplyFromResolver;
use PHPUnit\Framework\TestCase;

/**
 * A reply goes out from the address the mail was sent to.
 *
 * Every case is the same question — which of this account's addresses does the
 * original name — asked of a different place in the message, in the order the
 * resolver looks.
 */
final class ReplyFromResolverTest extends TestCase
{
    private ReplyFromResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ReplyFromResolver();
    }

    public function testMailSentToAnAliasIsAnsweredFromThatAlias(): void
    {
        $original = $this->original(to: ['Sales@Example.test']);

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    public function testAnAliasInCcCounts(): void
    {
        $original = $this->original(to: ['someone@elsewhere.test'], cc: ['sales@example.test']);

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    /** What the sender typed first is who they thought they were writing to. */
    public function testToIsPreferredOverCc(): void
    {
        $original = $this->original(to: ['sales@example.test'], cc: ['me@example.test']);

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    public function testMailToADisabledAliasIsNotAnsweredFromIt(): void
    {
        $original = $this->original(to: ['old@example.test']);

        self::assertNull($this->resolver->addressFor($original, $this->account()));
    }

    public function testMailThatNamesNoneOfTheAccountsAddressesHasNoAnswer(): void
    {
        $original = $this->original(to: ['list@elsewhere.test']);

        self::assertNull($this->resolver->addressFor($original, $this->account()));
    }

    /** Bcc and list mail: the address is only in what the server recorded. */
    public function testTheDeliveryHeadersAreReadWhenToAndCcNameNobody(): void
    {
        $original          = $this->original(to: ['list@elsewhere.test']);
        $original->headers = ['Delivered-To' => 'sales@example.test'];

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));

        // webklex spells header names lowercase with underscores.
        $original->headers = ['x_original_to' => '<Sales@example.test>'];

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    /** A forwarding hop rewrites Delivered-To; what the sender typed still wins. */
    public function testTheVisibleRecipientsWinOverTheDeliveryHeaders(): void
    {
        $original          = $this->original(to: ['sales@example.test']);
        $original->headers = ['Delivered-To' => 'me@example.test'];

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    public function testMailToACatchAllAddressIsAnsweredFromThatAddress(): void
    {
        $account                  = $this->account();
        $account->catchAllDomains = ['shop.test'];

        $original = $this->original(to: ['Yo@Shop.test']);

        self::assertSame('yo@shop.test', $this->resolver->addressFor($original, $account));
    }

    /** Answering your own sent mail continues it as whoever you were then. */
    public function testAReplyToYourOwnMailKeepsTheAddressItWasSentFrom(): void
    {
        $original              = $this->original(to: ['customer@elsewhere.test']);
        $original->fromAddress = 'sales@example.test';

        self::assertSame('sales@example.test', $this->resolver->addressFor($original, $this->account()));
    }

    /**
     * @param list<string> $to
     * @param list<string> $cc
     */
    private function original(array $to, array $cc = []): Message
    {
        $wrap = static fn (string $address): array => ['name' => '', 'address' => $address];

        $message              = new Message();
        $message->fromAddress = 'k@elsewhere.test';
        $message->toAddresses = array_map($wrap, $to);
        $message->ccAddresses = array_map($wrap, $cc);

        return $message;
    }

    private function account(): Account
    {
        $account        = new Account();
        $account->email = 'me@example.test';

        $account->addAlias(new EmailAlias($account, 'me@example.test', EmailAliasSource::Manual, EmailAliasStatus::Primary));
        $account->addAlias(new EmailAlias($account, 'sales@example.test', EmailAliasSource::Manual, EmailAliasStatus::Active));
        $account->addAlias(new EmailAlias($account, 'old@example.test', EmailAliasSource::Manual, EmailAliasStatus::Inactive));

        return $account;
    }
}
