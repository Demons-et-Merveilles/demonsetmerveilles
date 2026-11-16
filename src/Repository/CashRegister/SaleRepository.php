<?php

namespace App\Repository\CashRegister;

use App\Entity\CashRegister\Event;
use App\Entity\CashRegister\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    /**
     * Ventes d'un évènement avec leurs lignes (une seule requête).
     *
     * @return Sale[]
     */
    public function findByEventWithLines(Event $event, bool $newestFirst = true): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('l')
            ->leftJoin('s.lines', 'l')
            ->andWhere('s.event = :event')
            ->setParameter('event', $event)
            ->orderBy('s.createdAt', $newestFirst ? 'DESC' : 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
