<?php

declare(strict_types=1);

namespace App\Repository\Mail;

use App\Entity\Mail\InboxCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<InboxCategory>
 */
class InboxCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboxCategory::class);
    }

    /**
     * Where a new category goes: after every one the reader already has.
     *
     * One past the largest position rather than a count, because removing a
     * category leaves a gap and a count would then collide with the tab at the
     * end of the strip.
     */
    public function nextPositionFor(UserInterface $user): int
    {
        $largest = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->where('c.usr = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $largest ? 0 : (int) $largest + 1;
    }

    /**
     * Every category the user made, in the order their tabs are drawn.
     *
     * @return list<InboxCategory>
     */
    public function findForUserOrdered(UserInterface $user): array
    {
        /** @var list<InboxCategory> */
        return $this->findBy(['usr' => $user], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
