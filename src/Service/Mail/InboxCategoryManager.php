<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Domain\Enum\Mail\MessageCategory;
use App\Domain\Exception\InvalidInboxCategoryException;
use App\Entity\Mail\InboxCategory;
use App\Entity\User\User;
use App\Repository\Mail\InboxCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Making, renaming and removing the inbox tabs a reader made themselves.
 *
 * NAMES ARE CHECKED HERE, NOT BY A CONSTRAINT. Per reader and without regard to
 * case, which an index cannot say in Doctrine attributes (see InboxCategory),
 * and a built-in tab's name is refused outright: a strip with two tabs both
 * called Promotions, or one a reader typed beside the real one, is a strip
 * nobody can trust. The built-ins are refused by their English names — what the
 * enum stores — and not by their translated labels, which differ per language
 * and are the reader's to confuse themselves with.
 *
 * REMOVAL DOES WHAT THE DATABASE CANNOT. The two columns that file mail under a
 * category are ON DELETE SET NULL, so the foreign keys un-file the mail and the
 * conversations on their own. They cannot clear a PIN. A conversation the reader
 * dragged into the category carries MessageThread::$categoryPinnedAt, every
 * recompute skips a pinned thread, and once its category was gone it would
 * stay pinned to nothing for ever — filed in no tab of the reader's, never
 * resolved again, and nothing on screen to say why. So the pins of everything
 * in the category are cleared first, in the same transaction.
 *
 * A thread pinned to a BUILT-IN tab, or to another of the reader's categories,
 * is not touched: it was never pinned to this one.
 */
final readonly class InboxCategoryManager
{
    public function __construct(
        private InboxCategoryRepository $categories,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws InvalidInboxCategoryException
     */
    public function create(User $owner, string $name): InboxCategory
    {
        $category           = new InboxCategory();
        $category->usr      = $owner;
        $category->name     = $this->validName($owner, $name, null);
        $category->position = $this->categories->nextPositionFor($owner);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * @throws InvalidInboxCategoryException
     */
    public function rename(InboxCategory $category, string $name): void
    {
        $category->name = $this->validName($category->usr, $name, $category);

        $this->em->flush();
    }

    /**
     * Remove the category, un-file everything in it and free the pins into it.
     */
    public function remove(InboxCategory $category): void
    {
        $this->em->wrapInTransaction(function () use ($category): void {
            $this->em->getConnection()->executeStatement(
                'UPDATE message_thread SET category_pinned_at = NULL WHERE user_category_id = :id',
                ['id' => $category->id],
            );

            $this->em->remove($category);
            $this->em->flush();
        });
    }

    private function validName(User $owner, string $name, ?InboxCategory $renaming): string
    {
        // Unicode whitespace both ends: a name pasted with a non-breaking space
        // would otherwise be a tab nobody can tell from its neighbour.
        $name = (string) preg_replace('/^\s+|\s+$/u', '', $name);

        if ('' === $name) {
            throw new InvalidInboxCategoryException('settings.categories.error.empty');
        }

        if (mb_strlen($name) > InboxCategory::NAME_MAX_LENGTH) {
            throw new InvalidInboxCategoryException('settings.categories.error.too_long');
        }

        if (null !== MessageCategory::tryFrom(mb_strtolower($name))) {
            throw new InvalidInboxCategoryException('settings.categories.error.reserved');
        }

        foreach ($this->categories->findForUserOrdered($owner) as $existing) {
            if ($existing === $renaming) {
                continue;
            }

            if (mb_strtolower($existing->name) === mb_strtolower($name)) {
                throw new InvalidInboxCategoryException('settings.categories.error.taken');
            }
        }

        return $name;
    }
}
