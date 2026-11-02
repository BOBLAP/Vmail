<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\Helper\AddressHelper;
use App\Entity\User\User;

/** Private groups stored with the user's settings, included in normal backups. */
final class RecipientGroups
{
    public const string SETTING = User::SETTING_RECIPIENT_GROUPS;
    public const int MAX_MEMBERS = 500;
    public const int MAX_GROUPS = 100;

    public function all(User $user): array
    {
        $groups = $user->getSetting(self::SETTING, []);

        return is_array($groups) ? $groups : [];
    }

    /** @return list<string> Canonical, deduplicated addresses; no invalid fragment is discarded. */
    public function parse(string $text): array
    {
        // Extend the header splitter locally: incoming mail headers keep their RFC semantics.
        $parts = [];
        $current = '';
        $quoted = false;
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($quoted && '\\' === $char && $i + 1 < $length) {
                $current .= $char . $text[++$i];
                continue;
            }
            if ('"' === $char) {
                $quoted = !$quoted;
            } elseif (!$quoted && '<' === $char) {
                $depth++;
            } elseif (!$quoted && '>' === $char) {
                $depth = max(0, $depth - 1);
            }
            if (!$quoted && 0 === $depth && str_contains(",;\r\n", $char)) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;
        $emails = [];
        foreach ($parts as $part) {
            if ('' === trim($part)) {
                continue;
            }
            // AddressHelper::email alone would accept the last address in a malformed list.
            if (false === str_contains($part, '<')) {
                $email = trim($part);
            } elseif (1 === preg_match('/^(?:"(?:[^"\\\\]|\\\\.)*"|[^<>";,]*)\s*<([^<>]+)>\s*$/s', trim($part), $match)) {
                $email = trim($match[1]);
            } else {
                throw new \InvalidArgumentException('recipient_groups.invalid');
            }
            $email = mb_strtolower($email);
            if ($email !== AddressHelper::email($email) || false === AddressHelper::isValidEmail($email)) {
                throw new \InvalidArgumentException('recipient_groups.invalid');
            }
            $emails[$email] = $email;
        }
        if ([] === $emails || count($emails) > self::MAX_MEMBERS) {
            throw new \InvalidArgumentException('recipient_groups.invalid');
        }

        return array_values($emails);
    }
}
