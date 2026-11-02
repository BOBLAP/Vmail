<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Controller\ChecksCsrf;
use App\Entity\User\User;
use App\Form\RecipientGroupType;
use App\Service\Mail\RecipientGroups;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/settings/recipient-groups', name: 'app_settings_recipient_groups_')]
#[IsGranted('ROLE_USER')]
final class RecipientGroupController extends AbstractController
{
    use ChecksCsrf;

    #[Route('/save/{id}', name: 'save', defaults: ['id' => 'new'], methods: ['POST'])]
    public function save(string $id, Request $request, #[CurrentUser] User $user, RecipientGroups $library, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $groups = $library->all($user);
        if ('new' !== $id && !isset($groups[$id])) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(RecipientGroupType::class, null, [
            'action' => $this->generateUrl('app_settings_recipient_groups_save', ['id' => $id]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $emails = $library->parse($data['addresses']);
                $name = trim($data['name']);
                foreach ($groups as $key => $group) {
                    if ($key !== $id && mb_strtolower($group['name']) === mb_strtolower($name)) {
                        throw new \InvalidArgumentException('recipient_groups.duplicate');
                    }
                }
                if ('new' === $id && count($groups) >= RecipientGroups::MAX_GROUPS) {
                    throw new \InvalidArgumentException('recipient_groups.limit');
                }
                $groups['new' === $id ? bin2hex(random_bytes(12)) : $id] = ['name' => $name, 'emails' => $emails];
                uasort($groups, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
                $user->setSetting(RecipientGroups::SETTING, $groups);
                $em->flush();
                $this->addFlash('success', $translator->trans('recipient_groups.saved'));

                return $this->redirectToRoute('app_settings_index', ['section' => 'recipient-groups'], Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $error) {
                $form->addError(new FormError($translator->trans($error->getMessage())));
            }
        }
        $response = $this->forward(SettingsController::class . '::index', ['recipientGroupForm' => $form, 'recipientGroupId' => $id], ['section' => 'recipient-groups']);
        $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        return $response;
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $id, Request $request, #[CurrentUser] User $user, RecipientGroups $library, EntityManagerInterface $em): Response
    {
        $this->assertCsrf($request, 'recipient_group_delete_' . $id);
        $groups = $library->all($user);
        if (!isset($groups[$id])) {
            throw $this->createNotFoundException();
        }
        unset($groups[$id]);
        $user->setSetting(RecipientGroups::SETTING, $groups);
        $em->flush();

        return $this->redirectToRoute('app_settings_index', ['section' => 'recipient-groups'], Response::HTTP_SEE_OTHER);
    }
}
