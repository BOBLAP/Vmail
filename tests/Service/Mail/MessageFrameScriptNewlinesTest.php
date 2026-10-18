<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Service\Mail\MessageFrameScript;
use PHPUnit\Framework\TestCase;

final class MessageFrameScriptNewlinesTest extends TestCase
{
    public function testCspHashMatchesTheBytesTheHtmlParserWillExecute(): void
    {
        $root = sys_get_temp_dir().'/plmail-frame-'.bin2hex(random_bytes(8));
        mkdir($root.'/templates/mail', 0700, true);
        $file = $root.'/templates/mail/_frame_script.js';
        $source = "(function () {\n    parent.postMessage({ height: 200 }, '*');\n})();";
        try {
            foreach (["\n", "\r\n", "\r"] as $newline) {
                file_put_contents($file, str_replace("\n", $newline, $source).$newline);
                $script = new MessageFrameScript($root);
                self::assertSame($source, $script->source());
                self::assertSame('sha256-'.base64_encode(hash('sha256', $source, true)), $script->hash());
            }
        } finally {
            unlink($file);
            rmdir($root.'/templates/mail');
            rmdir($root.'/templates');
            rmdir($root);
        }
    }
}
