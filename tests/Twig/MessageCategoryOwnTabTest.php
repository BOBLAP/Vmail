<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Domain\Enum\Mail\LabelRole;
use App\Domain\Enum\Mail\MessageCategory;
use App\Entity\Mail\InboxCategory;
use App\Tests\Support\Mail\SeedsMarkerFixtures;
use App\Twig\MessageCategoryExtension;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The message panel says where a conversation IS, and that has to be able to say
 * "in a tab you made".
 *
 * Without it the panel reads the built-in column and announces "Promotions" about
 * a conversation sitting in the reader's Shipping tab — a true statement about a
 * column nobody is looking at, which is exactly the fault the panel's own
 * comments record having had twice before.
 */
final class MessageCategoryOwnTabTest extends KernelTestCase
{
    use SeedsMarkerFixtures;

    private Connection $connection;
    private MessageCategoryExtension $extension;

    protected function setUp(): void
    {
        self::bootKernel();

        $container        = self::getContainer();
        $this->em         = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get(Connection::class);
        $this->extension  = $container->get(MessageCategoryExtension::class);

        $this->connection->beginTransaction();

        $this->user    = $this->seedUser();
        $this->account = $this->seedAccount();
        $this->inbox   = $this->seedLabel('Inbox', LabelRole::Inbox);

        $container->get('security.token_storage')->setToken(
            new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()),
        );
    }

    protected function tearDown(): void
    {
        if (true === $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testAConversationInTheReadersOwnTabIsReportedByThatTabsName(): void
    {
        $shipping       = new InboxCategory();
        $shipping->usr  = $this->user;
        $shipping->name = 'Shipping';
        $this->em->persist($shipping);

        $thread               = $this->thread('a notice', MessageCategory::Promotions);
        $thread->userCategory = $shipping;
        $this->em->flush();

        $report = $this->extension->report($thread->messages->first());

        self::assertNotNull($report);
        self::assertSame('Shipping', $report['own']);
        self::assertSame('promotions', $report['filed'], 'the built-in category must still be reported as it is');
    }

    public function testAConversationInNoOwnTabOfTheirsHasNone(): void
    {
        $thread = $this->thread('a promo', MessageCategory::Promotions);

        $report = $this->extension->report($thread->messages->first());

        self::assertNotNull($report);
        self::assertNull($report['own']);
    }
}
