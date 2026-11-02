<?php

declare(strict_types=1);

namespace App\Service\Mail\PostIngest;

use App\Domain\DTO\Mail\PostIngestResult;
use App\Domain\Interface\PostIngestStepInterface;
use App\Infrastructure\Messaging\Message\SendAutoResponseMessage;
use App\Service\Mail\AutoResponder;
use App\Service\Mail\AutoResponsePolicy;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AutoResponseStep implements PostIngestStepInterface
{
    public function __construct(private AutoResponder $responder, private AutoResponsePolicy $policy, private MessageBusInterface $bus) {}

    public function afterCommit(PostIngestResult $result): void
    {
        // An initial import must never become an outbound mailing campaign.
        if (!$result->live) return;
        foreach ($result->messages as $message) {
            if (null === $message->id || null === $this->policy->recipient($message)) continue;
            $config = $this->responder->configuration($message->account->usr);
            if (!empty($config['campaign'])) $this->bus->dispatch(new SendAutoResponseMessage($message->id, $config['campaign']));
        }
    }
}
