<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Tous les évènements avec leurs sessions (une seule requête), les plus récents d'abord.
     *
     * @return list<Evenement>
     */
    public function tousAvecSessions(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('s')
            ->leftJoin('e.sessions', 's')
            ->orderBy('e.dateDebut', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
