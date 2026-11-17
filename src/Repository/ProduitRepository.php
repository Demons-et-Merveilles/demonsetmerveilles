<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Produits actifs, triés pour l'affichage en caisse.
     *
     * @return list<Produit>
     */
    public function actifs(): array
    {
        return $this->findBy(['actif' => true], ['categorie' => 'ASC', 'nom' => 'ASC']);
    }

    /**
     * Retrouve des produits à partir de leurs références, indexés par référence.
     *
     * @param list<string> $references
     * @return array<string, Produit>
     */
    public function indexParReference(array $references): array
    {
        if ($references === []) {
            return [];
        }

        $resultat = [];
        foreach ($this->findBy(['reference' => $references]) as $produit) {
            $resultat[$produit->getReference()] = $produit;
        }

        return $resultat;
    }
}
