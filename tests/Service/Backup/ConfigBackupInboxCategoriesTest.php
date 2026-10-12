<?php

declare(strict_types=1);

namespace App\Tests\Service\Backup;

use App\Entity\Mail\InboxCategory;
use App\Entity\Rule\MailRule;
use App\Entity\User\User;
use App\Infrastructure\Doctrine\Type\EncryptedStringType;
use App\Infrastructure\Encryption\Encryptor;
use App\Repository\Mail\InboxCategoryRepository;
use App\Repository\Rule\MailRuleRepository;
use App\Repository\User\UserRepository;
use App\Service\Backup\ConfigBackupExporter;
use App\Service\Backup\ConfigBackupImporter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A reader's own inbox tabs survive a restore, and so do the filters that file
 * into them.
 *
 * The category table is a per-user table the backup did not know about, which is
 * the class of omission ConfigBackupCompletenessTest exists to catch: nothing
 * fails when a table is simply never written, and a person restoring an install
 * gets their tabs silently gone. Worse here than for most tables, because a
 * filter's action names a category by ID — restored without the categories, or
 * with the old ids written through, a filter would file into nothing or into a
 * stranger's tab.
 *
 * So the ids are asserted against the ones the restore actually minted, and a
 * reference the document cannot satisfy is dropped rather than carried.
 */
final class ConfigBackupInboxCategoriesTest extends KernelTestCase
{
    private const string EMAIL = 'tabs@backup-categories.test';

    private const string OTHER_KEY = 'YW5vdGhlciBpbnN0YWxsYXRpb25zIGtleSwgMzIgYnk=';

    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private ConfigBackupExporter $exporter;
    private ConfigBackupImporter $importer;
    private Encryptor $originalEncryptor;

    protected function setUp(): void
    {
        self::bootKernel();

        $container               = static::getContainer();
        $this->entityManager     = $container->get(EntityManagerInterface::class);
        $this->exporter          = $container->get(ConfigBackupExporter::class);
        $this->importer          = $container->get(ConfigBackupImporter::class);
        $this->originalEncryptor = $container->get(Encryptor::class);
        $this->connection        = $this->entityManager->getConnection();

        $this->connection->beginTransaction();

        $this->emptyTheInstall();
    }

    protected function tearDown(): void
    {
        EncryptedStringType::setEncryptor($this->originalEncryptor);

        if (true === $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testTheCategoriesAndTheFiltersThatFileIntoThemComeBackTogether(): void
    {
        $sourceIds = $this->seedReader();

        $document = $this->exporter->document();

        $this->becomeADifferentInstallation();
        $this->importer->apply($document);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::EMAIL]);
        self::assertInstanceOf(User::class, $user);

        $categories = static::getContainer()->get(InboxCategoryRepository::class)->findForUserOrdered($user);

        // Order is the order of the strip, and it must survive.
        self::assertSame(['Shipping', 'Receipts'], array_map(static fn (InboxCategory $c): string => (string) $c->name, $categories));

        $shippingId = $categories[0]->id;
        self::assertNotContains($shippingId, $sourceIds, 'the fixture is void unless the restored ids differ from the source ones');

        $rules = static::getContainer()->get(MailRuleRepository::class)->findForUserOrdered($user);
        self::assertCount(1, $rules);

        self::assertSame(
            [['type' => 'fileInCategory', 'categoryId' => $shippingId], ['type' => 'markRead']],
            $rules[0]->actions,
            'the filter files into the category as it was restored, not the id it had on the old install',
        );
    }

    /**
     * A reference to a category the file does not carry is dropped, not carried
     * as a number that now names somebody else's row or nothing at all. The
     * action beside it survives, so "everything is dropped" cannot pass.
     */
    public function testAnActionNamingACategoryTheFileLacksIsDropped(): void
    {
        $this->seedReader();

        $document = $this->exporter->document();
        $document['users'][self::EMAIL]['rules'][0]['actions'][0]['categoryId'] = 987654;

        $this->becomeADifferentInstallation();
        $this->importer->apply($document);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $user  = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::EMAIL]);
        $rules = static::getContainer()->get(MailRuleRepository::class)->findForUserOrdered($user);

        self::assertSame([['type' => 'markRead']], $rules[0]->actions);
    }

    /**
     * Every backup written before this existed has no such key, and every one of
     * them has to keep restoring: no categories, no error.
     */
    public function testABackupWithoutCategoriesStillRestoresTheUser(): void
    {
        $this->seedReader();

        $document = $this->exporter->document();
        unset($document['users'][self::EMAIL]['inboxCategories']);
        // The filter that named one has nothing to name now.
        $document['users'][self::EMAIL]['rules'][0]['actions'] = [['type' => 'markRead']];

        $this->becomeADifferentInstallation();
        $this->importer->apply($document);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::EMAIL]);
        self::assertInstanceOf(User::class, $user);
        self::assertSame([], static::getContainer()->get(InboxCategoryRepository::class)->findForUserOrdered($user));
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    /**
     * One reader with two categories and a filter filing into the first, flushed
     * before the filter so its JSON carries the ids the sequence gave the rows.
     *
     * @return list<int> the ids the categories have on this (the source) install
     */
    private function seedReader(): array
    {
        $user            = new User();
        $user->email     = self::EMAIL;
        $user->nameFirst = 'Tabs';
        $user->nameLast  = 'Backup';
        $user->password  = '$2y$13$0123456789012345678901uJ7yCgMbNoMYVUFsyDhV0EjJb9dRSkq';
        $user->roles     = ['ROLE_USER'];
        $this->entityManager->persist($user);

        $shipping           = new InboxCategory();
        $shipping->usr      = $user;
        $shipping->name     = 'Shipping';
        $shipping->position = 0;
        $this->entityManager->persist($shipping);

        $receipts           = new InboxCategory();
        $receipts->usr      = $user;
        $receipts->name     = 'Receipts';
        $receipts->position = 1;
        $this->entityManager->persist($receipts);

        $this->entityManager->flush();

        $rule          = new MailRule();
        $rule->usr     = $user;
        $rule->name    = 'Carriers';
        $rule->actions = [
            ['type' => 'fileInCategory', 'categoryId' => $shipping->id],
            ['type' => 'markRead'],
        ];
        $this->entityManager->persist($rule);

        $this->entityManager->flush();
        $this->entityManager->clear();

        return [(int) $shipping->id, (int) $receipts->id];
    }

    private function emptyTheInstall(): void
    {
        $tables = array_filter(
            $this->connection->createSchemaManager()->listTableNames(),
            static fn (string $table): bool => 'doctrine_migration_versions' !== $table,
        );

        $this->connection->executeStatement(sprintf('TRUNCATE TABLE %s CASCADE', implode(', ', $tables)));
        $this->entityManager->clear();
    }

    private function becomeADifferentInstallation(): void
    {
        EncryptedStringType::setEncryptor(new Encryptor(self::OTHER_KEY));

        $this->emptyTheInstall();
    }
}
