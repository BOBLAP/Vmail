<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\Helper\RecipientHeaderHelper;
use App\Entity\Mail\Account;
use App\Entity\Mail\Message;

/**
 * Which of the account's addresses answers a message: the one it was sent to.
 *
 * A mailbox with three addresses is three correspondents to the people writing
 * to it. Answering mail that came in for `sales@` from `me@` tells the sender
 * an address they were never given, and on a catch-all domain it is the whole
 * reason the domain exists — the address somebody was handed is the one their
 * answer has to come from.
 *
 * Until this existed every reply opened on the account's primary address,
 * aliases included, and getting it right was a dropdown the user had to
 * remember on every mail.
 *
 * WHERE THE ADDRESS IS LOOKED FOR, IN ORDER
 *
 *  1. From, when the original is the user's own mail: answering something you
 *     sent continues it as whoever you were when you sent it.
 *  2. To, then Cc — what the sender typed.
 *  3. Delivered-To, X-Original-To, Envelope-To — what the server delivered to,
 *     which is the only place the address appears when the user was in Bcc or
 *     on a list. Last, because a forwarding hop rewrites these to an address
 *     nobody wrote to.
 *
 * The first address the account may send as wins (Account::maySendAs()). That
 * is the same rule the From selector is built from, so whatever comes out of
 * here is an option the selector can show and the send will accept. Null means
 * the message names none of them, and the caller stays on the primary address.
 */
final readonly class ReplyFromResolver
{
    private const array DELIVERY_HEADERS = ['delivered-to', 'x-original-to', 'envelope-to'];

    public function addressFor(Message $original, Account $account): ?string
    {
        foreach ($this->candidates($original) as $address) {
            $address = strtolower(trim($address));

            if (true === $account->maySendAs($address)) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidates(Message $original): array
    {
        $candidates = [(string) $original->fromAddress];

        foreach ([$original->toAddresses ?? [], $original->ccAddresses ?? []] as $list) {
            foreach ($list as $entry) {
                $candidates[] = (string) ($entry['address'] ?? '');
            }
        }

        $headers = $original->headers ?? [];

        foreach (self::DELIVERY_HEADERS as $field) {
            foreach (RecipientHeaderHelper::addresses($headers, $field) as $entry) {
                $candidates[] = $entry['address'];
            }
        }

        return $candidates;
    }
}
