<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\User\User;
use DateTimeImmutable;
use DateTimeZone;

/** One timed response per user, shared by their receiving accounts. Times are UTC. */
final class AutoResponder
{
    public function configuration(User $user): array
    {
        $value = $user->getSetting(User::SETTING_AUTO_RESPONDER, []);

        return is_array($value) ? $value : [];
    }

    public function active(User $user, ?DateTimeImmutable $now = null): bool
    {
        $config = $this->configuration($user);
        $now ??= new DateTimeImmutable();
        $start = $this->date($config['startsAt'] ?? null);
        $end = $this->date($config['endsAt'] ?? null);

        return true === ($config['enabled'] ?? false) && null !== $start && null !== $end
            && $start <= $now && $now < $end && '' !== trim((string) ($config['message'] ?? ''));
    }

    public function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }
        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    public function formData(User $user): array
    {
        $config = $this->configuration($user);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'enabled' => $config['enabled'] ?? false,
            'startsAt' => $this->date($config['startsAt'] ?? null) ?? $now,
            'endsAt' => $this->date($config['endsAt'] ?? null) ?? $now->modify('+7 days'),
            'format' => $config['format'] ?? 'text',
            'subject' => $config['subject'] ?? '',
            'message' => $config['message'] ?? '',
        ];
    }

    public function store(User $user, array $data, MailBodySanitizer $sanitizer, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable();
        $start = $data['startsAt'];
        $end = $data['endsAt'];
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $end <= $start
            || ($data['enabled'] && $end <= $now)) {
            throw new \InvalidArgumentException('auto_responder.invalid_period');
        }
        $body = 'html' === $data['format'] ? $sanitizer->sanitizeComposedBody($data['message']) : trim($data['message']);
        $visible = 'html' === $data['format'] ? html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $body;
        if ('' === trim(str_replace("\u{00A0}", ' ', $visible))) {
            throw new \InvalidArgumentException('auto_responder.empty_message');
        }
        $old = $this->configuration($user);
        $startText = $start->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
        $endText = $end->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
        $samePeriod = ($old['startsAt'] ?? null) === $startText && ($old['endsAt'] ?? null) === $endText;
        $user->setSetting(User::SETTING_AUTO_RESPONDER, [
            'enabled' => $data['enabled'], 'startsAt' => $startText, 'endsAt' => $endText,
            'format' => $data['format'], 'subject' => trim($data['subject']), 'message' => $body,
            'campaign' => $samePeriod && isset($old['campaign']) ? $old['campaign'] : bin2hex(random_bytes(16)),
            'activatedAt' => $samePeriod && true === ($old['enabled'] ?? false)
                ? ($old['activatedAt'] ?? $now->format(DATE_ATOM)) : $now->format(DATE_ATOM),
        ]);
    }
}
