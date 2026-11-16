<?php

namespace App\Service\CashRegister;

use App\Entity\CashRegister\Event;
use App\Entity\CashRegister\Sale;
use App\Entity\CashRegister\SaleLine;
use App\Repository\CashRegister\ProductRepository;
use App\Repository\CashRegister\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import / export de l'historique au format historique de historique_ventes.json :
 * une ligne JSON par article vendu, les lignes d'un même panier partagent "id_panier".
 */
class SalesHistoryJsonService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaleRepository $saleRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function export(Event $event): array
    {
        $rows = [];

        foreach ($this->saleRepository->findByEventWithLines($event, newestFirst: false) as $sale) {
            foreach ($sale->getLines() as $line) {
                $rows[] = [
                    'id_panier' => $sale->getReference(),
                    'id_article' => $line->getProductReference(),
                    'nom' => $line->getProductName(),
                    'quantite' => $line->getQuantity(),
                    'prix_unitaire' => (float) $line->getUnitPrice(),
                    'sous_total' => (float) $line->getSubtotal(),
                    'date' => $sale->getCreatedAt()->format(\DATE_ATOM),
                ];
            }
        }

        return $rows;
    }

    /**
     * Importe les paniers dans l'évènement donné.
     * Un panier dont l'id_panier existe déjà en base est ignoré (pas de doublon si on réimporte un fichier).
     *
     * @param array<int, mixed> $rows
     *
     * @return int nombre de paniers créés
     *
     * @throws \InvalidArgumentException si une ligne est invalide (rien n'est alors enregistré)
     */
    public function import(Event $event, array $rows): int
    {
        $cartsByReference = $this->groupByCart($rows);

        $created = 0;
        $productsByReference = []; // cache pour éviter une requête par ligne

        foreach ($cartsByReference as $cartReference => $cartRows) {
            if (null !== $this->saleRepository->findOneBy(['reference' => $cartReference])) {
                continue;
            }

            $sale = new Sale($event, (string) $cartReference, $this->parseDate($cartRows[0]['date']));

            foreach ($cartRows as $row) {
                $productReference = (string) $row['id_article'];

                if (!array_key_exists($productReference, $productsByReference)) {
                    $productsByReference[$productReference] = $this->productRepository->findOneBy(['reference' => $productReference]);
                }

                $sale->addLine(new SaleLine(
                    $productReference,
                    (string) ($row['nom'] ?? $productReference),
                    (int) $row['quantite'],
                    $row['prix_unitaire'],
                    $productsByReference[$productReference],
                ));
            }

            $this->em->persist($sale);
            ++$created;
        }

        $this->em->flush();

        return $created;
    }

    /**
     * Valide chaque ligne puis les regroupe par panier.
     *
     * @param array<int, mixed> $rows
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCart(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;

            if (!is_array($row)
                || !isset($row['id_panier'], $row['id_article'], $row['quantite'], $row['prix_unitaire'], $row['date'])
                || !is_numeric($row['quantite']) || (int) $row['quantite'] < 1
                || !is_numeric($row['prix_unitaire'])) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : champ manquant ou invalide.', $line));
            }

            try {
                $this->parseDate($row['date']);
            } catch (\Exception) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : date invalide.', $line));
            }

            $grouped[(string) $row['id_panier']][] = $row;
        }

        return $grouped;
    }

    /** Les dates du JSON sont en UTC ("...Z") : on les convertit dans le fuseau du serveur avant stockage. */
    private function parseDate(mixed $value): \DateTimeImmutable
    {
        return (new \DateTimeImmutable((string) $value))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
