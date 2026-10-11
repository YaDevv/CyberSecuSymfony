<?php

namespace App\Repository;

use App\Entity\LoginHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginHistory>
 */
class LoginHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginHistory::class);
    }

    /**
     * Toutes les connexions, regroupées par identifiant d'utilisateur,
     * de la plus récente à la plus ancienne.
     *
     * @return array<int, list<LoginHistory>>
     */
    public function findAllGroupedByUser(): array
    {
        $grouped = [];

        foreach ($this->findBy([], ['id' => 'DESC']) as $login) {
            $grouped[$login->getUser()->getId()][] = $login;
        }

        return $grouped;
    }
}
