<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\Ai\EmbeddingPreset;
use App\Entity\User\User;
use App\Repository\Ai\AiSettingsRepository;
use App\Repository\User\UserRepository;
use App\Service\Ai\OllamaClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Admin -> AI renders, and the settings card folds itself away once it is done.
 *
 * The fold exists because settings are a thing you FINISH. Once a host and a
 * model are on file, that card is a wall of filled-in fields standing between
 * the administrator and the two cards below it, which are the ones worth coming
 * back to.
 *
 * Rendering it at all is half the value of this file. Nothing else in the suite
 * renders `admin/ai/_frame.html.twig` — the panel test renders only the
 * telemetry partial — so the whole settings card, the form, and the `open`
 * expression that reads `form.vars.valid` had no coverage whatsoever. That
 * expression is evaluated on every visit to the page and would be a 500 on the
 * admin area, which is the kind of thing that is found in production.
 */
final class AiSettingsCardTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'e2e-admin@plmail.test';

    private KernelBrowser $client;
    private Connection $connection;
    private EntityManagerInterface $em;
    private AiSettingsRepository $settings;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container        = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->em         = $container->get(EntityManagerInterface::class);
        $this->settings   = $container->get(AiSettingsRepository::class);

        $admin = $container->get(UserRepository::class)->findOneBy(['email' => self::ADMIN_EMAIL]);

        if (false === $admin instanceof User) {
            self::markTestSkipped('run `app:test:seed-user --admin` first');
        }

        $this->client->loginUser($admin);

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (true === $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Nothing configured: open, because there is something to do in it.
     *
     * This is also the assertion that the page renders at all.
     */
    public function testAnUnconfiguredInstallationOpensTheCard(): void
    {
        $this->client->request('GET', '/admin/ai');

        self::assertResponseIsSuccessful();

        self::assertTrue(
            $this->settingsCardIsOpen(),
            'nothing is configured, so there is still something to do in this card',
        );
    }

    /**
     * The presets are on the page, carrying all three values.
     *
     * Rendered rather than reasoned about: the buttons hand their values to a
     * Stimulus controller through data attributes, so a rename on either side
     * is a button that silently does nothing when pressed. There is no error,
     * no console message and no failing request — the administrator simply
     * finds that clicking a preset fills nothing in.
     */
    public function testThePresetsOfferModelInstructionAndThresholdTogether(): void
    {
        $crawler = $this->client->request('GET', '/admin/ai');

        self::assertResponseIsSuccessful();

        $buttons = $crawler->filter('[data-action="admin--embedding-preset#apply"]');

        self::assertCount(count(EmbeddingPreset::cases()), $buttons, 'every preset is offered');

        $default = $buttons->reduce(
            static fn ($node): bool => EmbeddingPreset::Qwen3Embedding06b->value === $node->attr('data-model'),
        );

        self::assertCount(1, $default, 'the default preset is one of them');
        self::assertSame(
            EmbeddingPreset::Qwen3Embedding06b->queryInstruction(),
            $default->attr('data-instruction'),
            'the instruction travels with the model, newline and all',
        );
        self::assertSame(
            (string) EmbeddingPreset::Qwen3Embedding06b->minSimilarity(),
            $default->attr('data-similarity'),
        );
    }

    /**
     * And the two new fields are really bound to the entity.
     *
     * A form field that does not map writes nothing and reports success, which
     * on this page means an administrator choosing a preset, pressing Save,
     * seeing no error, and getting exactly the search they had before.
     */
    public function testTheInstructionAndThresholdAreSaved(): void
    {
        $crawler = $this->client->request('GET', '/admin/ai');
        $form    = $crawler->filter('form[name="ai_settings"]')->form();

        $form['ai_settings[embeddingModel]']         = EmbeddingPreset::Qwen3Embedding06b->value;
        $form['ai_settings[searchQueryInstruction]'] = "Instruct: find mail\nQuery: ";
        $form['ai_settings[semanticMinSimilarity]']  = '0.42';

        $this->client->submit($form);

        $this->em->clear();
        $stored = $this->settings->currentOrDefault();

        self::assertSame("Instruct: find mail\nQuery: ", $stored->searchQueryInstruction);
        self::assertSame(0.42, $stored->semanticMinSimilarity);
    }

    /** A host and both models on file: folded away, with the chip still speaking. */
    public function testAConfiguredInstallationFoldsTheCardAway(): void
    {
        $settings = $this->settings->currentOrDefault();

        $settings->baseUrl        = 'http://192.0.2.1:11434';
        $settings->chatModel      = 'a-writing-model';
        $settings->embeddingModel = 'a-search-model';

        if (null === $settings->id) {
            $this->em->persist($settings);
        }

        $this->em->flush();

        $this->client->request('GET', '/admin/ai');

        self::assertResponseIsSuccessful();

        self::assertFalse(
            $this->settingsCardIsOpen(),
            'a finished settings card should not stand in the way of the ones below it',
        );

        // The fold must not take the state with it: the status chip lives in the
        // summary, which is the half that stays on screen.
        self::assertStringContainsString('<summary', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Test asks the address on screen, with the key on screen, and stores
     * neither.
     *
     * The whole value of the button is that it answers BEFORE anything is
     * committed to. So the request has to go to what was typed, carry what was
     * typed, and leave the row exactly as it was.
     */
    public function testTheTestButtonAsksWhatWasTypedAndSavesNothing(): void
    {
        $asked = $this->recordModelHostRequests();

        $this->storeHost('http://192.0.2.1:11434', 'stored-key');

        $crawler = $this->client->request('GET', '/admin/ai');
        $form    = $crawler->filter('form[name="ai_settings"]')->form();

        $form['ai_settings[baseUrl]']  = 'http://192.0.2.7:11434';
        $form['ai_settings[apiToken]'] = 'typed-key';

        $this->client->request('POST', '/admin/ai/test', $form->getPhpValues());

        self::assertResponseIsSuccessful();
        self::assertNotSame([], $asked->getArrayCopy(), 'the host was never asked');

        foreach ($asked as $url => $headers) {
            self::assertStringStartsWith('http://192.0.2.7:11434/', $url);
            self::assertStringContainsString('Bearer typed-key', $headers);
        }

        self::assertStringNotContainsString('typed-key', (string) $this->client->getResponse()->getContent());

        $this->em->clear();
        $stored = $this->settings->currentOrDefault();

        self::assertSame('http://192.0.2.1:11434', $stored->baseUrl);
        self::assertSame('stored-key', $stored->apiToken);
    }

    /**
     * The stored key is not shown to an address it was not stored for.
     *
     * Testing a different address with the key box left empty is the ordinary
     * way to try a new host, and it must not be a way to post the old host's
     * credential to it.
     */
    public function testTestingAnotherAddressDoesNotTakeTheStoredKeyAlong(): void
    {
        $asked = $this->recordModelHostRequests();

        $this->storeHost('http://192.0.2.1:11434', 'stored-key');

        $crawler = $this->client->request('GET', '/admin/ai');
        $form    = $crawler->filter('form[name="ai_settings"]')->form();

        $form['ai_settings[baseUrl]'] = 'http://192.0.2.7:11434';

        $this->client->request('POST', '/admin/ai/test', $form->getPhpValues());

        self::assertNotSame([], $asked->getArrayCopy());

        foreach ($asked as $headers) {
            self::assertStringNotContainsString('stored-key', $headers);
        }
    }

    /** Without a valid token this action sends nothing anywhere. */
    public function testATestWithoutAValidCsrfTokenAsksNobody(): void
    {
        $asked = $this->recordModelHostRequests();

        $crawler = $this->client->request('GET', '/admin/ai');
        $values  = $crawler->filter('form[name="ai_settings"]')->form()->getPhpValues();

        $values['ai_settings']['baseUrl'] = 'http://192.0.2.7:11434';
        $values['ai_settings']['_token']  = 'not-the-token';

        $this->client->request('POST', '/admin/ai/test', $values);

        self::assertSame([], $asked->getArrayCopy());
    }

    /**
     * A model host that answers, and a record of what it was asked with.
     *
     * The kernel is kept across requests so the replaced client survives from
     * the GET that renders the form to the POST that tests it.
     *
     * @return \ArrayObject<string, string> request headers by URL
     */
    private function recordModelHostRequests(): \ArrayObject
    {
        $this->client->disableReboot();

        /** @var \ArrayObject<string, string> $asked */
        $asked = new \ArrayObject();

        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($asked): MockResponse {
            $asked[$url] = implode("\n", $options['headers'] ?? []);

            return new MockResponse((string) json_encode(['models' => [], 'version' => '0.0.0']));
        });

        static::getContainer()->set(OllamaClient::class, new OllamaClient($http, new NullLogger()));

        return $asked;
    }

    private function storeHost(string $address, string $key): void
    {
        $settings = $this->settings->currentOrDefault();

        $settings->baseUrl  = $address;
        $settings->apiToken = $key;

        if (null === $settings->id) {
            $this->em->persist($settings);
        }

        $this->em->flush();
    }

    /**
     * Whether the first <details> on the page carries the `open` attribute.
     *
     * Matched against the OPENING TAG and as a whole word, which the first
     * version of this was not — and it was worthless for it. The summary's
     * chevron carries `group-open:rotate-90`, so a plain search for "open"
     * anywhere in the markup is true no matter what the card does: the
     * assertion passed, its negation could never pass, and neither of them was
     * looking at the thing under test.
     */
    private function settingsCardIsOpen(): bool
    {
        $html  = (string) $this->client->getResponse()->getContent();
        $start = strpos($html, '<details');

        self::assertNotFalse($start, 'the settings card did not render at all');

        $end = strpos($html, '>', $start);
        $tag = substr($html, $start, (int) $end - $start);

        return 1 === preg_match('/\bopen\b/', $tag);
    }
}
