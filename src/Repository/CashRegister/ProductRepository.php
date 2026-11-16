<?php

namespace App\Repository\CashRegister;

use App\Entity\CashRegister\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Articles affichés dans la grille de la caisse (actifs uniquement).
     *
     * @return Product[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->innerJoin('p.category', 'c')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
