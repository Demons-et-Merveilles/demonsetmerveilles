<?php

namespace App\Repository\CashRegister;

use App\Entity\CashRegister\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Catégories qui contiennent au moins un article actif (boutons de filtre de la caisse).
     *
     * @return Category[]
     */
    public function findWithActiveProducts(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.products', 'p')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->distinct()
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
