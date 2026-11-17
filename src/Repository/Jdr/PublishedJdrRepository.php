<?php

namespace App\Repository\Jdr;

use App\Entity\Jdr\JdrType;
use App\Entity\Jdr\PublishedJdr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublishedJdr>
 */
class PublishedJdrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublishedJdr::class);
    }

    /**
     * Toutes les cartes dans l'ordre d'affichage (MJ chargés dans la même requête).
     *
     * @return list<PublishedJdr>
     */
    public function ordered(): array
    {
        return $this->createQueryBuilder('j')
            ->addSelect('g')
            ->leftJoin('j.gameMaster', 'g')
            ->orderBy('j.displayOrder', 'ASC')
            ->addOrderBy('j.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Uniquement les JDR (pas les invités) : utilisé par la page d'impression.
     *
     * @return list<PublishedJdr>
     */
    public function onlyJdr(): array
    {
        return $this->createQueryBuilder('j')
            ->addSelect('g')
            ->leftJoin('j.gameMaster', 'g')
            ->where('j.type = :type')
            ->setParameter('type', JdrType::Jdr->value)
            ->orderBy('j.displayOrder', 'ASC')
            ->addOrderBy('j.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Prochain numéro d'ordre d'affichage (pour ajouter une carte à la fin)
    public function nextDisplayOrder(): int
    {
        $max = $this->createQueryBuilder('j')
            ->select('MAX(j.displayOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
