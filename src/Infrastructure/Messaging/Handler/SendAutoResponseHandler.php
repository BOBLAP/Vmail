<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Handler;

use App\Infrastructure\Messaging\Message\SendAutoResponseMessage;
use App\Infrastructure\Messaging\Message\SendMessageMessage;
use App\Repository\Mail\MessageRepository;
use App\Service\Mail\AutoResponder;
use App\Service\Mail\AutoResponseOutbox;
use App\Service\Mail\AutoResponsePolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendAutoResponseHandler
{
    public function __construct(private MessageRepository $messages, private EntityManagerInterface $em, private AutoResponder $responder, private AutoResponsePolicy $policy, private AutoResponseOutbox $outbox, private SendMessageHandler $send) {}

    public function __invoke(SendAutoResponseMessage $job): void
    {
        $source = $this->messages->find($job->sourceId);
        if (!$source || !$source->account->usr) return;
        // Workers may have loaded these entities before a move, stop or account edit.
        $this->em->refresh($source);
        $this->em->refresh($source->account);
        $this->em->refresh($source->account->usr);
        $config = $this->responder->configuration($source->account->usr);
        if (($config['campaign'] ?? null) !== $job->campaign || null === ($recipient = $this->policy->recipient($source))) return;
        $replyId = $this->outbox->prepare($source, $recipient, $config);
        if (!$replyId) return;
        $reply = $this->messages->find($replyId);
        if (!$reply || null !== $reply->sentAt || $reply->cancelled) return;
        ($this->send)(new SendMessageMessage($replyId));
        $reply = $this->messages->find($replyId);
        if ($reply && null === $reply->sentAt && null === $reply->sendClaimedAt) {
            throw new \RuntimeException('Automatic reply transport refused delivery; retry through the export queue.');
        }
    }
}
