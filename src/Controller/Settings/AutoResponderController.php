<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Controller\ChecksCsrf;
use App\Entity\User\User;
use App\Form\AutoResponderType;
use App\Service\Mail\AutoResponder;
use App\Service\Mail\MailBodySanitizer;
use App\Service\User\UserTimezoneResolver;
use App\Twig\AutoResponderGlobal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/settings/auto-responder', name: 'app_settings_auto_responder_')]
#[IsGranted('ROLE_USER')]
final class AutoResponderController extends AbstractController
{
    use ChecksCsrf;

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request, #[CurrentUser] User $user, AutoResponder $responder, MailBodySanitizer $sanitizer, UserTimezoneResolver $timezones, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(AutoResponderType::class, $responder->formData($user), [
            'timezone' => $timezones->nameFor($user), 'action' => $this->generateUrl('app_settings_auto_responder_save'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $responder->store($user, $form->getData(), $sanitizer);
                $em->flush();
                $this->addFlash('success', $translator->trans('auto_responder.saved'));
                return $this->redirectToRoute('app_settings_index', ['section' => 'auto-responder'], Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $error) {
                $form->addError(new FormError($translator->trans($error->getMessage())));
            }
        }
        $response = $this->forward(SettingsController::class . '::index', ['autoResponderForm' => $form], ['section' => 'auto-responder']);
        $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        return $response;
    }

    #[Route('/stop', name: 'stop', methods: ['POST'])]
    public function stop(Request $request, #[CurrentUser] User $user, AutoResponder $responder, EntityManagerInterface $em): Response
    {
        $this->assertCsrf($request, 'auto_responder_stop');
        $config = $responder->configuration($user);
        $config['enabled'] = false;
        $user->setSetting(User::SETTING_AUTO_RESPONDER, $config);
        $em->flush();
        return $this->redirectToRoute('app_settings_index', ['section' => 'auto-responder'], Response::HTTP_SEE_OTHER);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(AutoResponderGlobal $responder): JsonResponse
    {
        return $this->json($responder->getState(), headers: ['Cache-Control' => 'private, no-store']);
    }
}
