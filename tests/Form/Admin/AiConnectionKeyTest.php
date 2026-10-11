<?php

declare(strict_types=1);

namespace App\Tests\Form\Admin;

use App\Domain\Enum\Ai\AiProvider;
use App\Entity\Ai\AiSettings;
use App\Form\Admin\AiSettingsType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * What happens to a stored key when the form it sits in is saved.
 *
 * The key's field is never filled from storage, so it arrives empty on every
 * save that did not retype it. Empty therefore cannot mean "clear it" — and it
 * cannot mean "keep it" unconditionally either, because the address beside it
 * may have changed, and a key kept across that goes to a host it was never
 * issued for.
 */
final class AiConnectionKeyTest extends KernelTestCase
{
    private FormFactoryInterface $forms;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->forms = self::getContainer()->get('form.factory');
    }

    /** The ordinary save: nothing about the connection was touched. */
    public function testAnEmptyBoxKeepsTheKeyWhileTheAddressStays(): void
    {
        $settings = $this->submit('http://10.0.0.5:11434', '');

        self::assertSame('stored-key', $settings->apiToken);
    }

    /** Pasted with a slash, saved without one: the same host. */
    public function testATrailingSlashIsNotANewAddress(): void
    {
        $settings = $this->submit('http://10.0.0.5:11434/', '');

        self::assertSame('stored-key', $settings->apiToken);
    }

    /**
     * The case the rule exists for. Somebody changes the address and saves;
     * the box for the key is empty because it always is.
     */
    public function testAChangedAddressDropsTheKeyThatBelongedToTheOldOne(): void
    {
        $settings = $this->submit('https://somewhere.else.example/ollama', '');

        self::assertNull($settings->apiToken, 'a key issued for one host must not be sent to another');
    }

    /**
     * The provider is part of what a key was issued for.
     *
     * The same address under a different provider is, as far as plMail can
     * tell, a different thing answering — and the stored key was typed for the
     * one that was there before.
     */
    public function testAChangedProviderDropsTheKeyToo(): void
    {
        $settings = $this->submit('http://10.0.0.5:11434', '', 'openai');

        self::assertSame(AiProvider::OpenAi, $settings->provider);
        self::assertNull($settings->apiToken);
    }

    /** A new address with a new key typed beside it keeps the new key. */
    public function testATypedKeyIsTheKeyWhateverTheAddressDid(): void
    {
        self::assertSame('new-key', $this->submit('https://somewhere.else.example/ollama', 'new-key')->apiToken);
        self::assertSame('new-key', $this->submit('http://10.0.0.5:11434', 'new-key')->apiToken);
    }

    /** Never rendered back, on the way in or on the way out. */
    public function testTheKeyIsNotPutBackOnThePage(): void
    {
        $settings = $this->stored();

        $form = $this->forms->create(AiSettingsType::class, $settings, ['csrf_protection' => false]);

        self::assertSame('', (string) $form->createView()->children['apiToken']->vars['value']);
    }

    private function submit(string $address, string $key, string $provider = 'ollama'): AiSettings
    {
        $settings = $this->stored();

        $form = $this->forms->create(AiSettingsType::class, $settings, ['csrf_protection' => false]);

        $form->submit(['provider' => $provider, 'baseUrl' => $address, 'apiToken' => $key], false);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        return $settings;
    }

    private function stored(): AiSettings
    {
        $settings = new AiSettings();
        $settings->baseUrl  = 'http://10.0.0.5:11434';
        $settings->apiToken = 'stored-key';

        return $settings;
    }
}
