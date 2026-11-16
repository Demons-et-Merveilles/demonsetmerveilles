<?php

namespace App\Repository\Jdr;

use App\Entity\Jdr\GameTable;
use App\Entity\Jdr\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /** Plus grand numéro de place utilisé à cette table (0 si la table est vide). */
    public function findMaxSeatOrder(GameTable $table): int
    {
        $max = $this->createQueryBuilder('p')
            ->select('MAX(p.seatOrder)')
            ->andWhere('p.gameTable = :table')
            ->setParameter('table', $table)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max;
    }
}
