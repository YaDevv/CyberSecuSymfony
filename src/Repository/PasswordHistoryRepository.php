<?php

namespace App\Repository;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordHistory>
 */
class PasswordHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordHistory::class);
    }

    /**
     * Hachés des derniers mots de passe de l'utilisateur, du plus récent au plus ancien.
     *
     * @return list<string>
     */
    public function findLastHashes(User $user, int $limit): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.hash')
            ->andWhere('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values($rows);
    }
}
