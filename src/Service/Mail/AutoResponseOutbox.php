<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\Helper\MessageIdHelper;
use App\Entity\Mail\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The unique claim and draft are committed together, before any external send. */
final readonly class AutoResponseOutbox
{
    public function __construct(private EntityManagerInterface $em, private DraftPersister $drafts, private ReplyFromResolver $from, private TranslatorInterface $translator) {}

    public function prepare(Message $source, string $recipient, array $config): ?int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $id = $connection->fetchOne('INSERT INTO auto_response_delivery (usr_id, campaign, recipient, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON CONFLICT (usr_id, campaign, recipient) DO NOTHING RETURNING id', [$source->account->usr->id, $config['campaign'], $recipient]);
            if (false === $id) {
                $replyId = $connection->fetchOne('SELECT reply_id FROM auto_response_delivery WHERE usr_id = ? AND campaign = ? AND recipient = ?', [$source->account->usr->id, $config['campaign'], $recipient]);
                $connection->commit();
                // Deleting the Sent copy must not allow another automatic reply.
                return $replyId ? (int) $replyId : null;
            }
            $reply = new Message();
            $reply->hasAttachments = false;
            $reply->account = $source->account;
            $reply->fromAddress = $this->from->addressFor($source, $source->account)
                ?? $source->account->displayAddress ?? $source->account->email;
            $reply->toAddresses = [['address' => $recipient, 'name' => '']];
            $reply->ccAddresses = [];
            $reply->bccAddresses = [];
            $reply->subject = $config['subject'] ?: $this->translator->trans('auto_responder.reply_subject', [], null, $source->account->usr->locale);
            $reply->bodyHtml = 'html' === $config['format'] ? $config['message'] : null;
            $reply->bodyText = 'html' === $config['format'] ? $this->drafts->plainTextBody($config['message']) : $config['message'];
            $reply->headers = ['auto-submitted' => 'auto-replied'];
            $originalId = MessageIdHelper::normalise((string) $source->messageId);
            $reply->inReplyTo = '' === $originalId ? [] : [$originalId];
            $reply->references = $reply->inReplyTo;
            $this->drafts->save($reply, $source->account, $reply->fromAddress);
            $connection->executeStatement('UPDATE auto_response_delivery SET reply_id = ? WHERE id = ?', [$reply->id, $id]);
            $connection->commit();

            return $reply->id;
        } catch (\Throwable $error) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            throw $error;
        }
    }
}
