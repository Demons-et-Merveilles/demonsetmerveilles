<?php

namespace App\Repository;

use App\Entity\SessionVente;
use App\Entity\Vente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vente>
 */
class VenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vente::class);
    }

    /**
     * Ventes d'une session avec leurs lignes, de la plus ancienne à la plus récente.
     *
     * @return list<Vente>
     */
    public function ventesDeSession(SessionVente $session): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('l')
            ->join('v.lignes', 'l')
            ->where('v.session = :session')
            ->setParameter('session', $session)
            ->orderBy('v.dateVente', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de ventes et chiffre d'affaires de chaque session, indexés par id de session.
     * Les sessions sans vente n'apparaissent pas dans le résultat.
     *
     * @return array<int, array{nbVentes: int, total: float}>
     */
    public function statsParSession(): array
    {
        $lignes = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.session) AS sessionId', 'COUNT(DISTINCT v.id) AS nbVentes', 'SUM(l.sousTotal) AS total')
            ->join('v.lignes', 'l')
            ->groupBy('v.session')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($lignes as $ligne) {
            $stats[(int) $ligne['sessionId']] = [
                'nbVentes' => (int) $ligne['nbVentes'],
                'total' => (float) $ligne['total'],
            ];
        }

        return $stats;
    }
}
