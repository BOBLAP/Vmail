<?php

declare(strict_types=1);

namespace App\Tests\Domain\Helper;

use App\Domain\Helper\OfficeAttachment;
use PHPUnit\Framework\TestCase;

final class OfficeAttachmentTest extends TestCase
{
    public function testMimeTypesAndBinaryFallback(): void
    {
        foreach (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'] as $extension) {
            self::assertSame($extension, OfficeAttachment::extension('application/octet-stream; charset=binary', 'Test.'.strtoupper($extension)));
        }
        self::assertSame('docx', OfficeAttachment::extension('APPLICATION/VND.OPENXMLFORMATS-OFFICEDOCUMENT.WORDPROCESSINGML.DOCUMENT; name=x', 'wrong.html'));
        self::assertSame('doc', OfficeAttachment::extension('application/msword', null));
        foreach (['image/png', 'text/html', 'application/pdf', null] as $type) {
            self::assertNull(OfficeAttachment::extension($type, 'invoice.docx'));
        }
        foreach (['evil.html', 'macro.docm', 'macro.xlsm', 'unknown.zip', null] as $name) {
            self::assertNull(OfficeAttachment::extension('application/octet-stream', $name));
        }
    }
}
