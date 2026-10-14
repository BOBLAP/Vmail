<?php

declare(strict_types=1);

namespace App\Tests\Controller\Settings;

use App\Entity\Mail\InboxCategory;
use App\Entity\Rule\MailRule;
use App\Entity\User\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The card where a reader makes their own tabs, from the browser's end.
 *
 * What is pinned: the card is there and works end to end through the real
 * forms; a refused name says why and changes nothing; and every write is the
 * reader's OWN — another reader's category id in the URL is refused, and leaves
 * their category exactly as it was.
 */
final class InboxCategoryControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;
    private User $user;

    protected function tearDown(): void
    {
        if (isset($this->connection) && true === $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testTheCardIsOnTheGeneralPageAndListsTheReadersCategories(): void
    {
        $client = $this->signIn();
        $this->category($this->user, 'Shipping');

        $crawler = $client->request('GET', '/settings?section=general');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Your own tabs', $crawler->text());
        self::assertSame('Shipping', $crawler->filter('[data-inbox-categories] input[name="name"]')->first()->attr('value'));
    }

    public function testACategoryIsCreatedThroughTheRealForm(): void
    {
        $client  = $this->signIn();
        $crawler = $client->request('GET', '/settings?section=general');

        $form = $crawler->filter('form[action$="/settings/categories"]')->form(['name' => '  Shipping ']);
        $client->submit($form);

        self::assertResponseRedirects('/settings?section=general');

        $rows = $this->connection->fetchFirstColumn('SELECT name FROM inbox_category WHERE usr_id = :id', ['id' => $this->user->id]);
        self::assertSame(['Shipping'], $rows);

        $client->followRedirect();
        // Escaped, because it is read back out of the page's markup.
        self::assertStringContainsString('Created &quot;Shipping&quot;', (string) $client->getResponse()->getContent());
    }

    public function testARefusedNameSaysWhyAndChangesNothing(): void
    {
        $client = $this->signIn();
        $this->category($this->user, 'Shipping');

        $crawler = $client->request('GET', '/settings?section=general');
        $client->submit($crawler->filter('form[action$="/settings/categories"]')->form(['name' => 'shipping']));

        self::assertResponseRedirects('/settings?section=general');
        $client->followRedirect();

        self::assertStringContainsString('You already have a category with that name', (string) $client->getResponse()->getContent());
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inbox_category WHERE usr_id = :id', ['id' => $this->user->id]),
        );
    }

    public function testACategoryIsRenamedAndDeleted(): void
    {
        $client   = $this->signIn();
        $shipping = $this->category($this->user, 'Shipping');

        $crawler = $client->request('GET', '/settings?section=general');
        $client->submit($crawler->filter('form[action$="/settings/categories/' . $shipping->id . '/rename"]')->form(['name' => 'Parcels']));

        self::assertResponseRedirects();
        self::assertSame('Parcels', $this->connection->fetchOne('SELECT name FROM inbox_category WHERE id = :id', ['id' => $shipping->id]));

        $crawler = $client->request('GET', '/settings?section=general');
        $client->submit($crawler->filter('form[action$="/settings/categories/' . $shipping->id . '/delete"]')->form());

        self::assertResponseRedirects();
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inbox_category WHERE id = :id', ['id' => $shipping->id]));
    }

    /**
     * The id in the URL is the reader's input and ownership is the only thing
     * between it and somebody else's tab. Both writes, and the category must be
     * exactly as it was — name, and still there.
     */
    public function testSomebodyElsesCategoryCanNeitherBeRenamedNorDeleted(): void
    {
        $client  = $this->signIn();
        $theirs  = $this->category($this->seedUser('stranger'), 'Theirs');
        $token   = $this->token($client);

        $client->request('POST', '/settings/categories/' . $theirs->id . '/rename', ['_token' => $token, 'name' => 'Mine now']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/settings/categories/' . $theirs->id . '/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);

        self::assertSame('Theirs', $this->connection->fetchOne('SELECT name FROM inbox_category WHERE id = :id', ['id' => $theirs->id]));
    }

    public function testAWriteWithoutTheTokenIsRefused(): void
    {
        $client = $this->signIn();
        $client->request('GET', '/settings?section=general');

        $client->request('POST', '/settings/categories', ['name' => 'Sneaky']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inbox_category WHERE usr_id = :id', ['id' => $this->user->id]));
    }

    /**
     * The filter list is where a reader checks what a filter files into, so its
     * chip names the category — and says so when the category has gone, the way
     * it does for a deleted label.
     */
    public function testAFilterChipNamesItsCategoryAndSaysWhenItIsGone(): void
    {
        $client   = $this->signIn();
        $shipping = $this->category($this->user, 'Shipping');

        $rule          = new MailRule();
        $rule->usr     = $this->user;
        $rule->name    = 'Carriers';
        $rule->actions = [['type' => 'fileInCategory', 'categoryId' => $shipping->id]];
        $this->em->persist($rule);
        $this->em->flush();

        $client->request('GET', '/settings?section=filters');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Shipping', (string) $client->getResponse()->getContent());

        $this->connection->executeStatement('DELETE FROM inbox_category WHERE id = :id', ['id' => $shipping->id]);

        $client->request('GET', '/settings?section=filters');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('(deleted category)', (string) $client->getResponse()->getContent());
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    private function token(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/settings?section=general');

        return (string) $crawler->filter('input[name="_token"][value]')->last()->attr('value');
    }

    private function category(User $owner, string $name): InboxCategory
    {
        $category       = new InboxCategory();
        $category->usr  = $owner;
        $category->name = $name;
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    private function signIn(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        $container        = static::getContainer();
        $this->em         = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get(Connection::class);

        $this->connection->beginTransaction();

        $this->user = $this->seedUser('reader');
        $client->loginUser($this->user);

        return $client;
    }

    private function seedUser(string $tag): User
    {
        $user            = new User();
        $user->email     = 'categories-' . $tag . '-' . uniqid('', true) . '@example.test';
        $user->nameFirst = 'Category';
        $user->nameLast  = 'Card';
        $user->roles     = ['ROLE_USER'];
        $user->password  = '$2y$04$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOP';

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
