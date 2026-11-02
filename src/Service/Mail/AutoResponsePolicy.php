<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\Enum\Mail\LabelRole;
use App\Domain\Helper\AddressHelper;
use App\Domain\Helper\OwnAddressHelper;
use App\Entity\Mail\Message;
use DateTimeImmutable;

final readonly class AutoResponsePolicy
{
    public function __construct(private AutoResponder $responder, private HeaderNormalizer $headers) {}

    /** A positive result names exactly one recipient; bulk, old and automatic mail is never answered. */
    public function recipient(Message $message, ?DateTimeImmutable $now = null): ?string
    {
        $now ??= new DateTimeImmutable();
        $account = $message->account;
        $user = $account->usr;
        if (!$user || true !== $account->isActive || !$this->responder->active($user, $now)
            || $message->isSetAside() || $message->isDraft() || OwnAddressHelper::sentByAccount($message)) {
            return null;
        }
        foreach ($message->labels as $label) {
            if (in_array($label->role, [LabelRole::Sent, LabelRole::Drafts], true)) return null;
        }
        $config = $this->responder->configuration($user);
        $arrived = $message->providerAcceptedAt ?? $message->receivedAt;
        $start = $this->responder->date($config['startsAt'] ?? null);
        $activated = $this->responder->date($config['activatedAt'] ?? null);
        if (!$arrived || !$start || !$activated || $arrived < $start || $arrived < $activated || $arrived > $now->modify('+5 minutes')) return null;

        $headers = $this->headers->normalize($message->headers ?? []);
        foreach (['list-id', 'list-unsubscribe', 'list-post'] as $name) {
            if (array_key_exists($name, $headers)) return null;
        }
        foreach ((array) ($headers['auto-submitted'] ?? []) as $value) {
            if ('no' !== strtolower(trim(explode(';', $value)[0]))) return null;
        }
        if (preg_match('/\b(all|oof|autoreply)\b/i', implode(',', (array) ($headers['x-auto-response-suppress'] ?? [])))) return null;
        if (preg_match('/\b(bulk|list|junk)\b/i', implode(',', (array) ($headers['precedence'] ?? [])))) return null;
        if (str_contains(strtolower(implode(',', (array) ($headers['content-type'] ?? []))), 'multipart/report')) return null;
        if (preg_match('/^(yes|true)\b/i', $this->headers->first($headers, 'x-spam-status') ?? '')) return null;
        if (isset($headers['return-path']) && '' === trim((string) $this->headers->first($headers, 'return-path'), " <>\r\n\t")) return null;

        // Automatic replies follow the envelope sender when available, never an arbitrary Reply-To.
        $recipient = AddressHelper::email($this->headers->first($headers, 'return-path') ?? $message->fromAddress);
        if (!AddressHelper::isValidEmail($recipient) || preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)([+._-]|@)/i', $recipient)) return null;
        $own = OwnAddressHelper::forMessage($message);
        foreach ($user->accounts as $owned) $own = array_merge($own, $owned->ownedAddresses);
        if (in_array($recipient, $own, true)) return null;

        return $recipient;
    }
}
