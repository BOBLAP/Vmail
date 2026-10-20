<?php

declare(strict_types=1);

namespace App\Service\Mail;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Private, bounded Office-to-PDF cache. Original PDFs never pass through this service.
 *
 * Only uploaded bytes reach Gotenberg, never user-supplied URLs. One shared lock
 * bounds conversions across PHP workers; an atomic rename publishes completed PDFs.
 */
final readonly class OfficePreviewConverter
{
    public const int MAX_INPUT_BYTES = 25 * 1024 * 1024;
    public const int MAX_OUTPUT_BYTES = 50 * 1024 * 1024;
    private const int CACHE_TTL = 7 * 86400;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $projectDir,
        private string $gotenbergUrl,
    ) {}

    public function enabled(): bool
    {
        return '' !== $this->gotenbergUrl;
    }

    public function pdfPath(string $source, int $partId, string $extension): string
    {
        if (!$this->enabled()) {
            throw new OfficePreviewException('attachment.office.unavailable', 503);
        }
        if (!in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'], true)) {
            throw new OfficePreviewException('attachment.office.failed');
        }
        $size = filesize($source);
        if (false === $size || $size > self::MAX_INPUT_BYTES) {
            throw new OfficePreviewException('attachment.office.too_large', 413);
        }
        $directory = $this->projectDir.'/var/attachments/office-previews';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new OfficePreviewException('attachment.office.unavailable', 503);
        }
        $hash = hash_file('sha256', $source);
        if (false === $hash) {
            throw new OfficePreviewException('attachment.office.failed');
        }
        $path = sprintf('%s/v1-%d-%s-%s.pdf', $directory, $partId, $hash, $extension);
        if ($this->cached($path)) {
            return $path;
        }
        $lock = fopen($directory.'/conversion.lock', 'c');
        if (false === $lock) {
            throw new OfficePreviewException('attachment.office.unavailable', 503);
        }
        @chmod($directory.'/conversion.lock', 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new OfficePreviewException('attachment.office.busy', 429);
        }
        $temporary = null;
        $output = null;
        $response = null;
        try {
            if ($this->cached($path)) {
                return $path;
            }
            // Prune expired derivatives on cache misses; originals are in other directories.
            foreach (glob($directory.'/*.pdf') ?: [] as $old) {
                if (filemtime($old) < time() - self::CACHE_TTL) {
                    @unlink($old);
                }
            }
            $form = new FormDataPart([
                'files' => new DataPart(new File($source), 'document.'.$extension),
            ]);
            $response = $this->httpClient->request('POST', rtrim($this->gotenbergUrl, '/').'/forms/libreoffice/convert', [
                'headers' => $form->getPreparedHeaders()->toArray(),
                'body' => $form->bodyToIterable(),
                'timeout' => 10,
                'max_duration' => 25,
                'max_redirects' => 0,
                'buffer' => false,
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new OfficePreviewException('attachment.office.failed');
            }
            $temporary = tempnam($directory, '.converting-');
            $output = false !== $temporary ? fopen($temporary, 'wb') : false;
            if (false === $output) {
                throw new OfficePreviewException('attachment.office.unavailable', 503);
            }
            $total = 0;
            foreach ($this->httpClient->stream($response) as $chunk) {
                $bytes = $chunk->getContent();
                $total += strlen($bytes);
                if ($total > self::MAX_OUTPUT_BYTES || strlen($bytes) !== fwrite($output, $bytes)) {
                    throw new OfficePreviewException('attachment.office.failed');
                }
            }
            fclose($output);
            $output = null;
            if ($total < 5 || '%PDF-' !== file_get_contents($temporary, false, null, 0, 5)) {
                throw new OfficePreviewException('attachment.office.failed');
            }
            if (!rename($temporary, $path)) {
                throw new OfficePreviewException('attachment.office.unavailable', 503);
            }
            $temporary = null;
            return $path;
        } catch (OfficePreviewException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new OfficePreviewException('attachment.office.unavailable', 503, $error);
        } finally {
            $response?->cancel();
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_string($temporary)) {
                @unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function cached(string $path): bool
    {
        return is_file($path) && filemtime($path) >= time() - self::CACHE_TTL;
    }
}
