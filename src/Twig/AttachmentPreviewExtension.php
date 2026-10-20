<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Helper\PdfAttachment;
use App\Domain\Helper\OfficeAttachment;
use App\Service\Mail\OfficePreviewConverter;
use App\Entity\Mail\MessagePart;
use App\Service\Mail\AttachmentThumbnailer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `attachment_has_preview(part)` — whether the chip should show a thumbnail.
 *
 * Answers from the part's own columns, so a thread with a dozen attachments
 * costs a dozen string comparisons and no I/O. The image is only decoded when
 * the browser actually asks for the thumbnail URL.
 */
final class AttachmentPreviewExtension extends AbstractExtension
{
    public function __construct(private readonly AttachmentThumbnailer $thumbnailer, private readonly OfficePreviewConverter $officeConverter)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('attachment_has_preview', $this->hasPreview(...)),
            new TwigFunction('attachment_is_pdf', $this->isPdf(...)),
            new TwigFunction('attachment_can_open', $this->canOpen(...)),
        ];
    }

    public function isPdf(MessagePart $part): bool
    {
        return PdfAttachment::matches($part->contentType, $part->filename);
    }

    /**
     * Whether the chip opens the reader: PDF directly, Office through Gotenberg.
     * This is separate from hasPreview(), which only asks for a raster thumbnail.
     * The gate reads metadata only; displaying a thread never starts conversion.
     */
    public function canOpen(MessagePart $part): bool
    {
        return $this->isPdf($part) || ($this->officeConverter->enabled()
            && null !== OfficeAttachment::extension($part->contentType, $part->filename));
    }

    public function hasPreview(MessagePart $part): bool
    {
        return $this->thumbnailer->isPreviewable($part);
    }
}
