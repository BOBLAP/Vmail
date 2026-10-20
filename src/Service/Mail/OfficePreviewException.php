<?php

declare(strict_types=1);

namespace App\Service\Mail;

final class OfficePreviewException extends \RuntimeException
{
    public function __construct(public readonly string $translationKey, public readonly int $status = 422, ?\Throwable $previous = null)
    {
        parent::__construct($translationKey, 0, $previous);
    }
}
