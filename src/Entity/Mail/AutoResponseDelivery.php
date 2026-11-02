<?php

declare(strict_types=1);

namespace App\Entity\Mail;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auto_response_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_auto_response_recipient', columns: ['usr_id', 'campaign', 'recipient'])]
class AutoResponseDelivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $usr;

    #[ORM\Column(length: 32)]
    public string $campaign;

    #[ORM\Column(length: 320)]
    public string $recipient;

    #[ORM\ManyToOne(targetEntity: Message::class), ORM\JoinColumn(name: 'reply_id', nullable: true, onDelete: 'SET NULL')]
    public ?Message $reply = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
