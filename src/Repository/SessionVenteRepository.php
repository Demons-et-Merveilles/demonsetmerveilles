<?php

namespace App\Repository;

use App\Entity\SessionVente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionVente>
 */
class SessionVenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionVente::class);
    }

    /**
     * Sessions utilisables en caisse : non archivées, sur un évènement non archivé.
     *
     * @return list<SessionVente>
     */
    public function ouvertes(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('e')
            ->join('s.evenement', 'e')
            ->where('s.archiveeLe IS NULL')
            ->andWhere('e.archive = :archive')
            ->setParameter('archive', false)
            ->orderBy('s.ouverteLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
