<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Service\Mail\OfficePreviewConverter;
use App\Service\Mail\OfficePreviewException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OfficePreviewConverterTest extends TestCase
{
    private string $directory;
    private string $source;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/office-preview-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        $this->source = $this->directory.'/source.docx';
        file_put_contents($this->source, 'fixture');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testCacheReuseAndContentInvalidation(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function ($method, $url, $options) use (&$calls) {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('http://gotenberg:3000/forms/libreoffice/convert', $url);
            self::assertSame(0, $options['max_redirects']);
            return new MockResponse('%PDF-1.7 fixture', ['http_code' => 200]);
        });
        $converter = new OfficePreviewConverter($http, $this->directory, 'http://gotenberg:3000');
        $path = $converter->pdfPath($this->source, 1, 'docx');
        self::assertSame('%PDF-1.7 fixture', file_get_contents($path));
        self::assertSame($path, $converter->pdfPath($this->source, 1, 'docx'));
        self::assertSame(1, $calls);
        file_put_contents($this->source, 'different content');
        self::assertNotSame($path, $converter->pdfPath($this->source, 1, 'docx'));
        self::assertSame(2, $calls);
    }

    public function testInvalidOutputIsNotPublishedAndLockIsReleased(): void
    {
        $http = new MockHttpClient([new MockResponse('<html>not a PDF</html>'), new MockResponse('%PDF-1.7 valid')]);
        $converter = new OfficePreviewConverter($http, $this->directory, 'http://gotenberg:3000');
        try {
            $converter->pdfPath($this->source, 1, 'docx');
            self::fail('Invalid output must fail');
        } catch (OfficePreviewException $error) {
            self::assertSame('attachment.office.failed', $error->translationKey);
        }
        self::assertSame([], glob($this->directory.'/var/attachments/office-previews/*.pdf'));
        self::assertSame([], glob($this->directory.'/var/attachments/office-previews/.converting-*'));
        self::assertFileExists($converter->pdfPath($this->source, 1, 'docx'));
    }

    public function testDisabledServiceAndSizeLimitDoNotSendDocuments(): void
    {
        $http = new MockHttpClient(static function () { self::fail('No request should be sent'); });
        try {
            new OfficePreviewConverter($http, $this->directory, '')->pdfPath($this->source, 1, 'docx');
            self::fail('Disabled conversion must fail');
        } catch (OfficePreviewException $error) {
            self::assertSame(503, $error->status);
        }
        $file = fopen($this->source, 'wb');
        ftruncate($file, OfficePreviewConverter::MAX_INPUT_BYTES + 1);
        fclose($file);
        $this->expectException(OfficePreviewException::class);
        $this->expectExceptionMessage('attachment.office.too_large');
        new OfficePreviewConverter($http, $this->directory, 'http://gotenberg:3000')->pdfPath($this->source, 1, 'docx');
    }

    public function testServiceFailureDoesNotPoisonTheCache(): void
    {
        $converter = new OfficePreviewConverter(new MockHttpClient(new MockResponse('unavailable', ['http_code' => 503])), $this->directory, 'http://gotenberg:3000');
        $this->expectException(OfficePreviewException::class);
        $converter->pdfPath($this->source, 1, 'xlsx');
    }
    public function testBusyConverterDoesNotSendAnotherRequest(): void
    {
        $cache = $this->directory.'/var/attachments/office-previews';
        mkdir($cache, 0700, true);
        $lock = fopen($cache.'/conversion.lock', 'c');
        flock($lock, LOCK_EX);
        $http = new MockHttpClient(static function () { self::fail('The lock must prevent another conversion'); });
        try {
            new OfficePreviewConverter($http, $this->directory, 'http://gotenberg:3000')->pdfPath($this->source, 1, 'docx');
            self::fail('A second conversion must be refused');
        } catch (OfficePreviewException $error) {
            self::assertSame(429, $error->status);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testOversizedOutputIsDiscarded(): void
    {
        $body = static function () {
            yield '%PDF-';
            for ($i = 0; $i < 51; ++$i) {
                yield str_repeat('x', 1024 * 1024);
            }
        };
        $converter = new OfficePreviewConverter(new MockHttpClient(new MockResponse($body())), $this->directory, 'http://gotenberg:3000');
        try {
            $converter->pdfPath($this->source, 1, 'pptx');
            self::fail('Oversized output must be refused');
        } catch (OfficePreviewException $error) {
            self::assertSame('attachment.office.failed', $error->translationKey);
        }
        self::assertSame([], glob($this->directory.'/var/attachments/office-previews/*.pdf'));
        self::assertSame([], glob($this->directory.'/var/attachments/office-previews/.converting-*'));
    }

}
