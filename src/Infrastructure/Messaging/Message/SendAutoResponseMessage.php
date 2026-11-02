<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Message;

final readonly class SendAutoResponseMessage
{
    public function __construct(public int $sourceId, public string $campaign) {}
}
