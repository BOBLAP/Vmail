<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Domain\Enum\Mail\SyncFrequency;
use App\Entity\User\User;
use App\Repository\Mail\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class SyncFrequencyController extends AbstractController
{
    #[Route('/settings/sync-frequency', name: 'app_settings_sync_frequency', methods: ['POST'])]
    public function save(
        Request $request,
        AccountRepository $accounts,
        EntityManagerInterface $entities,
        TranslatorInterface $translator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('sync-frequency', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $back = ['section' => 'sync-frequency'];
        $submittedIntervals = $request->request->all('interval');
        $intervals = [];
        foreach (SyncFrequency::cases() as $group) {
            $minutes = filter_var($submittedIntervals[$group->value] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => SyncFrequency::MIN_MINUTES, 'max_range' => SyncFrequency::MAX_MINUTES],
            ]);
            if (false === $minutes) {
                $this->addFlash('error', $translator->trans('sync_frequency.invalid'));

                return $this->redirectToRoute('app_settings_index', $back, Response::HTTP_SEE_OTHER);
            }
            $intervals[$group->value] = $minutes;
        }

        $groups = $request->request->all('group');
        foreach ($accounts->findForUserOrdered($user) as $account) {
            $value = $groups[$account->id] ?? null;
            $group = is_string($value) ? SyncFrequency::tryFrom($value) : null;
            if (null !== $group) {
                $account->setSetting(SyncFrequency::ACCOUNT_SETTING, $group->value);
            }
        }

        $user->setSetting(SyncFrequency::USER_SETTING, $intervals);
        $entities->flush();
        $this->addFlash('success', $translator->trans('sync_frequency.saved'));

        return $this->redirectToRoute('app_settings_index', $back, Response::HTTP_SEE_OTHER);
    }
}
