<?php

declare(strict_types=1);

namespace App\Controller\Mail;

use App\Domain\Helper\InlineDisposition;
use App\Domain\Helper\PdfAttachment;
use App\Domain\Helper\OfficeAttachment;
use App\Service\Mail\OfficePreviewConverter;
use App\Service\Mail\OfficePreviewException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Mail\MessagePart;
use App\Entity\Mail\Message;
use App\Security\Voter\OwnershipVoter;
use App\Service\Mail\AttachmentResolver;
use App\Service\Mail\AttachmentThumbnailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AttachmentController extends AbstractController
{
    public function __construct(
        private readonly AttachmentResolver $attachmentResolver,
        private readonly AttachmentThumbnailer $thumbnailer,
    ) {}

    /** Bundle the same non-inline parts shown in the message, retaining original bytes. */
    #[Route('/mail/message/{id}/attachments.zip', name: 'app_mail_attachments_zip', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function downloadAll(Message $message): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $message);
        $parts = $message->messageParts->filter(static fn (MessagePart $part): bool => !$part->isInline);
        if ($parts->isEmpty()) {
            throw $this->createNotFoundException();
        }

        $path = tempnam(sys_get_temp_dir(), 'plmail-zip-');
        if (false === $path) {
            throw new \RuntimeException('Cannot create attachment archive.');
        }
        $zip = new \ZipArchive();
        try {
            if (true !== $zip->open($path, \ZipArchive::OVERWRITE)) {
                throw new \RuntimeException('Cannot open attachment archive.');
            }
            $used = [];
            foreach ($parts as $part) {
                $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $part);
                $base = InlineDisposition::headerFilename($part->filename);
                if (in_array($base, ['.', '..'], true)) {
                    $base = 'attachment';
                }
                $name = $base;
                $suffix = 2;
                // Duplicate names must not silently replace an earlier attachment.
                while (isset($used[strtolower($name)])) {
                    $extension = pathinfo($base, PATHINFO_EXTENSION);
                    $stem = '' === $extension ? $base : substr($base, 0, -strlen($extension) - 1);
                    $name = $stem.' ('.$suffix++.')'.('' === $extension ? '' : '.'.$extension);
                }
                $used[strtolower($name)] = true;
                if (!$zip->addFile($this->attachmentResolver->absolutePathFor($part), $name)) {
                    throw new \RuntimeException('Cannot add attachment to archive.');
                }
            }
            if (!$zip->close()) {
                throw new \RuntimeException('Cannot finish attachment archive.');
            }
        } catch (\Throwable $error) {
            try {
                $zip->close();
            } catch (\Throwable) {
                // The archive may already be closed, or opening it may have failed.
            }
            @unlink($path);
            throw $error;
        }

        clearstatcache(true, $path);
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'attachments-'.$message->id.'.zip');
        $response->deleteFileAfterSend(true);
        return $response;
    }

    /** The shared reader: native PDFs stay in the browser; Office bytes are converted separately. */
    #[Route('/mail/attachment/{id}/preview', name: 'app_mail_attachment_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(MessagePart $part, OfficePreviewConverter $converter): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $part);

        $isPdf = PdfAttachment::matches($part->contentType, $part->filename);
        if (!$isPdf && (!$converter->enabled() || null === OfficeAttachment::extension($part->contentType, $part->filename))) {
            throw $this->createNotFoundException();
        }

        return $this->render('mail/_attachment_preview.html.twig', ['part' => $part, 'isOffice' => !$isPdf]);
    }

    /** The expensive operation is a CSRF-protected POST, never an automatic email thumbnail. */
    #[Route('/mail/attachment/{id}/preview.pdf', name: 'app_mail_attachment_office_pdf', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function officePdf(
        MessagePart $part,
        Request $request,
        OfficePreviewConverter $converter,
        TranslatorInterface $translator,
        LoggerInterface $logger,
    ): Response {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $part);
        if (!$this->isCsrfTokenValid('ajax', $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException();
        }
        $extension = OfficeAttachment::extension($part->contentType, $part->filename);
        if (null === $extension) {
            throw $this->createNotFoundException();
        }
        // Release the session lock before conversion so other tabs can keep using mail.
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }
        try {
            if (!$converter->enabled()) {
                throw new OfficePreviewException('attachment.office.unavailable', 503);
            }
            if (($part->size ?? 0) > OfficePreviewConverter::MAX_INPUT_BYTES) {
                throw new OfficePreviewException('attachment.office.too_large', 413);
            }
            $source = $this->attachmentResolver->absolutePathFor($part);
            $path = $converter->pdfPath($source, (int) $part->id, $extension);
        } catch (OfficePreviewException $error) {
            $logger->warning('Office preview failed', ['partId' => $part->id, 'exception' => $error]);
            $response = $this->json(['message' => $translator->trans($error->translationKey)], $error->status);
            $response->headers->set('Cache-Control', 'private, no-store');
            if (429 === $error->status) {
                $response->headers->set('Retry-After', '5');
            }
            return $response;
        } catch (\Throwable $error) {
            $logger->warning('Office attachment unavailable', ['partId' => $part->id, 'exception' => $error]);
            return $this->json(['message' => $translator->trans('attachment.office.unavailable')], 503, ['Cache-Control' => 'private, no-store']);
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', InlineDisposition::SANDBOX_CSP);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'preview.pdf');
        return $response;
    }

    /**
     * A cached preview for an image attachment.
     *
     * Separate from serve() so the thumbnail can be cached hard by the browser
     * and, more importantly, so a part that turns out not to be decodable
     * answers 404 and lets the chip keep its icon, rather than sending the
     * full-size original under a preview's name.
     */
    #[Route('/mail/attachment/{id}/thumbnail', name: 'app_mail_attachment_thumbnail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function thumbnail(MessagePart $part): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $part);

        $path = $this->thumbnailer->thumbnailPath($part);

        if (null === $path) {
            throw $this->createNotFoundException('No preview for this attachment.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'image/webp');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'preview.webp');

        // The bytes behind a part never change, so this is safe to hold on to.
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }

    #[Route('/mail/attachment/{id}', name: 'app_mail_attachment', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function serve(MessagePart $part, Request $request): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $part);

        $absolutePath = $this->attachmentResolver->absolutePathFor($part);
        $contentType  = $part->contentType ?? 'application/octet-stream';

        // An allow-list, not a prefix test. `image/svg+xml` starts with
        // "image/" and executes script when loaded as a document, and the type
        // comes from the MIME headers of an incoming mail — see
        // InlineDisposition, which carries the reasoning and the reachability.
        $inlineAllowed = InlineDisposition::allows($contentType);
        $forceDownload = true === $request->query->getBoolean('download')
            || false === $inlineAllowed;

        $response = new BinaryFileResponse($absolutePath);
        // Not $contentType: the sender chose it. See InlineDisposition::servedType().
        $response->headers->set('Content-Type', InlineDisposition::servedType($contentType));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // On every response, download or not: it costs an inline image nothing
        // and it means a future widening of the allow-list is a bug rather than
        // a vulnerability.
        $response->headers->set('Content-Security-Policy', InlineDisposition::SANDBOX_CSP);
        $response->setContentDisposition(
            true === $forceDownload
                ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
                : ResponseHeaderBag::DISPOSITION_INLINE,
            InlineDisposition::headerFilename($part->filename),
        );

        return $response;
    }

}
