<?php

declare(strict_types=1);

namespace App\Domain\Helper;

/** Office formats accepted by the preview converter, shared by Twig and routes. */
final readonly class OfficeAttachment
{
    private const array TYPES = [
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
    ];

    public static function extension(?string $contentType, ?string $filename): ?string
    {
        $type = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        if (isset(self::TYPES[$type])) {
            return self::TYPES[$type];
        }
        // As for PdfAttachment, only a generic binary type may defer to the name.
        if ('application/octet-stream' !== $type) {
            return null;
        }
        $extension = strtolower(pathinfo(trim((string) $filename), PATHINFO_EXTENSION));
        return in_array($extension, self::TYPES, true) ? $extension : null;
    }
}
