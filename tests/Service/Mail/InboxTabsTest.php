<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Domain\Enum\Mail\MessageCategory;
use App\Entity\Mail\InboxCategory;
use App\Entity\User\User;
use App\Repository\Mail\InboxCategoryRepository;
use App\Service\Mail\InboxTabs;
use PHPUnit\Framework\TestCase;

/**
 * The cost and the privacy of resolving a tab.
 *
 * Cost: the inbox has a query budget, and somebody who never made a category
 * must not pay for a feature they are not using. So the repository is a mock
 * that FAILS when it is asked for something it should not have been — every
 * refusal below is a case where the answer is known without a query.
 *
 * Privacy: the categories read are the asking reader's, and a worker that serves
 * two readers in a row must not answer the second from the first's memo.
 */
final class InboxTabsTest extends TestCase
{
    public function testABuiltInKeyAndAKeyThatCannotBeCustomCostNoQuery(): void
    {
        $repository = $this->createMock(InboxCategoryRepository::class);
        $repository->expects(self::never())->method('findForUserOrdered');

        $tabs   = new InboxTabs($repository);
        $reader = $this->user('reader');

        self::assertSame('promotions', $tabs->resolve($reader, 'promotions')?->value);
        self::assertNull($tabs->resolve($reader, ''));
        self::assertNull($tabs->resolve($reader, 'quatsch'));
        self::assertNull($tabs->resolve($reader, 'c1x'));
        self::assertNull($tabs->resolve($reader, 'cprimary'));
    }

    public function testReadingTheReadersCategoriesHappensOnceHoweverManyAsk(): void
    {
        $repository = $this->createMock(InboxCategoryRepository::class);
        $repository->expects(self::once())->method('findForUserOrdered')->willReturn([]);

        $tabs   = new InboxTabs($repository);
        $reader = $this->user('reader');

        $tabs->resolve($reader, 'c12');
        $tabs->resolve($reader, 'c40');
        $tabs->customFor($reader);
        $tabs->allFor($reader);
    }

    /**
     * The presence companion for the two above: a key that could be custom, from
     * a reader who has such a category, DOES resolve — so "never queries" cannot
     * be a resolver that never resolves.
     */
    public function testAKeyThatIsTheirsResolvesAndAnotherReadersDoesNot(): void
    {
        $shipping = $this->category('Shipping', 12);

        $repository = $this->createStub(InboxCategoryRepository::class);
        $repository->method('findForUserOrdered')->willReturnCallback(
            static fn (User $user): array => 'reader' === $user->getUserIdentifier() ? [$shipping] : [],
        );

        $tabs = new InboxTabs($repository);

        self::assertSame('c12', $tabs->resolve($this->user('reader'), 'c12')?->value);
        self::assertSame('Shipping', $tabs->resolve($this->user('reader'), 'c12')?->name);
        self::assertNull($tabs->resolve($this->user('stranger'), 'c12'), 'resolved a category the asking reader does not own');
    }

    public function testAllForListsTheFiveThenTheReadersOwnInTheirOrder(): void
    {
        $repository = $this->createStub(InboxCategoryRepository::class);
        $repository->method('findForUserOrdered')->willReturn([$this->category('Shipping', 12), $this->category('Receipts', 40)]);

        $values = array_map(
            static fn ($tab): string => $tab->value,
            (new InboxTabs($repository))->allFor($this->user('reader')),
        );

        self::assertSame(
            [...array_map(static fn (MessageCategory $c): string => $c->value, MessageCategory::cases()), 'c12', 'c40'],
            $values,
        );
    }

    public function testAResetForgetsTheLastReader(): void
    {
        $repository = $this->createMock(InboxCategoryRepository::class);
        $repository->expects(self::exactly(2))->method('findForUserOrdered')->willReturn([]);

        $tabs = new InboxTabs($repository);

        $tabs->customFor($this->user('reader'));
        $tabs->reset();
        $tabs->customFor($this->user('reader'));
    }

    private function user(string $email): User
    {
        $user        = new User();
        $user->email = $email;

        return $user;
    }

    /** A category with an id: the property is private(set), so it is persisted-shaped by reflection. */
    private function category(string $name, int $id): InboxCategory
    {
        $category       = new InboxCategory();
        $category->name = $name;

        new \ReflectionProperty(InboxCategory::class, 'id')->setValue($category, $id);

        return $category;
    }
}
