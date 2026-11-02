<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User\User;
use App\Service\Mail\AutoResponder;
use App\Service\User\UserTimezoneResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AutoResponderGlobal
{
    public function __construct(private Security $security, private AutoResponder $responder, private UserTimezoneResolver $timezones, private TranslatorInterface $translator) {}

    public function getState(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return ['enabled' => false, 'active' => false, 'startsAt' => null, 'endsAt' => null, 'label' => ''];
        $config = $this->responder->configuration($user);
        $end = $this->responder->date($config['endsAt'] ?? null);

        return [
            'enabled' => true === ($config['enabled'] ?? false),
            'active' => $this->responder->active($user),
            'startsAt' => $config['startsAt'] ?? null,
            'endsAt' => $config['endsAt'] ?? null,
            'label' => $end ? $this->translator->trans('auto_responder.active_until', ['%date%' => $end->setTimezone($this->timezones->resolve($user))->format('d/m/Y H:i')]) : '',
        ];
    }
}
