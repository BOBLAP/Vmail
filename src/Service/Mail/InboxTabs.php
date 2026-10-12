<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\DTO\Mail\InboxTab;
use App\Domain\Enum\Mail\MessageCategory;
use App\Repository\Mail\InboxCategoryRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The tabs a reader has: the five built-in ones and the ones they made.
 *
 * One place that knows how a tab key in a URL, a drop target or a posted body
 * becomes a tab, because each of those is untrusted input and each of them used
 * to be `MessageCategory::tryFrom()`. The ownership check lives here too: a
 * custom key resolves only against the categories THIS reader owns, so another
 * person's category, or one that has since been deleted, is simply not a tab.
 *
 * NOBODY WHO NEVER MADE A CATEGORY PAYS FOR THIS. A key that could not be a
 * custom one — every built-in name, an empty string, a mistyped URL — is
 * answered without a query, and the reader's categories are read at most once
 * per request however many callers ask. The inbox page has a query budget
 * (ThreadListQueryBudgetTest) and this feature must not spend it on the people
 * it is not for.
 *
 * Memoised for the request, and resettable for the reason NewMailMarkers is: a
 * worker serves many readers one after another, and a memo that outlived its
 * request would show the first reader's tabs to the second.
 */
final class InboxTabs implements ResetInterface
{
    /** @var array<string, list<InboxTab>> user identifier => their own tabs */
    private array $custom = [];

    public function __construct(
        private readonly InboxCategoryRepository $categories,
    ) {
    }

    public function reset(): void
    {
        $this->custom = [];
    }

    /**
     * The reader's own tabs, in the order they arranged them.
     *
     * @return list<InboxTab>
     */
    public function customFor(UserInterface $user): array
    {
        return $this->custom[$user->getUserIdentifier()] ??= $this->read($user);
    }

    /**
     * Every tab the reader could be shown: the built-in five, then their own.
     *
     * @return list<InboxTab>
     */
    public function allFor(UserInterface $user): array
    {
        return [
            ...array_map(InboxTab::builtIn(...), MessageCategory::cases()),
            ...$this->customFor($user),
        ];
    }

    /**
     * The tab a key names for this reader, or null when it names none of theirs.
     */
    public function resolve(UserInterface $user, string $key): ?InboxTab
    {
        $builtIn = MessageCategory::tryFrom($key);

        if (null !== $builtIn) {
            return InboxTab::builtIn($builtIn);
        }

        if (false === InboxTab::looksCustom($key)) {
            return null;
        }

        $names = [];

        foreach ($this->customFor($user) as $tab) {
            $names[(int) $tab->categoryId] = (string) $tab->name;
        }

        return InboxTab::parse($key, $names);
    }

    /**
     * @return list<InboxTab>
     */
    private function read(UserInterface $user): array
    {
        $tabs = [];

        foreach ($this->categories->findForUserOrdered($user) as $category) {
            $tabs[] = InboxTab::custom((int) $category->id, (string) $category->name);
        }

        return $tabs;
    }
}
