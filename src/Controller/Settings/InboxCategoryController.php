<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Controller\ChecksCsrf;
use App\Domain\Exception\InvalidInboxCategoryException;
use App\Entity\Mail\InboxCategory;
use App\Entity\User\User;
use App\Security\Voter\OwnershipVoter;
use App\Service\Mail\InboxCategoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The inbox tabs a reader makes themselves — Settings → General.
 *
 * Plain form posts that redirect back to the page, like the sorting card beside
 * it: three small writes on a short list do not need a frame of their own, and
 * a refused name comes back as a toast saying why rather than as a half-redrawn
 * card. Every write is the reader's own — the id in the URL is checked against
 * the owner before anything is changed.
 *
 * What fills a tab is not here. A category is only a name; mail goes into it
 * through a filter's "File in category" action (Settings → Filters).
 */
#[Route('/settings/categories', name: 'app_settings_categories_')]
#[IsGranted('ROLE_USER')]
final class InboxCategoryController extends AbstractController
{
    use ChecksCsrf;

    private const string CSRF = 'settings-categories';

    public function __construct(
        private readonly InboxCategoryManager $manager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->assertCsrf($request, self::CSRF);

        /** @var User $user */
        $user = $this->getUser();

        try {
            $category = $this->manager->create($user, (string) $request->request->get('name', ''));
        } catch (InvalidInboxCategoryException $e) {
            $this->addFlash('error', $this->translator->trans($e->translationKey));

            return $this->back();
        }

        $this->addFlash('success', $this->translator->trans('settings.categories.created', ['%name%' => $category->name]));

        return $this->back();
    }

    #[Route('/{id}/rename', name: 'rename', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rename(Request $request, InboxCategory $category): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $category);
        $this->assertCsrf($request, self::CSRF);

        try {
            $this->manager->rename($category, (string) $request->request->get('name', ''));
        } catch (InvalidInboxCategoryException $e) {
            $this->addFlash('error', $this->translator->trans($e->translationKey));

            return $this->back();
        }

        $this->addFlash('success', $this->translator->trans('settings.categories.renamed', ['%name%' => $category->name]));

        return $this->back();
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, InboxCategory $category): Response
    {
        $this->denyAccessUnlessGranted(OwnershipVoter::OWN, $category);
        $this->assertCsrf($request, self::CSRF);

        $name = $category->name;

        $this->manager->remove($category);

        $this->addFlash('success', $this->translator->trans('settings.categories.deleted', ['%name%' => $name]));

        return $this->back();
    }

    private function back(): Response
    {
        return $this->redirectToRoute('app_settings_index', ['section' => 'general']);
    }
}
