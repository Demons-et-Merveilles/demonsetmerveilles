<?php

namespace App\Service\CashRegister;

use App\Entity\CashRegister\Event;
use App\Entity\CashRegister\Sale;
use App\Entity\CashRegister\SaleLine;
use App\Repository\CashRegister\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre un panier encaissé.
 *
 * Le navigateur n'envoie que des ids et des quantités : les noms et les prix
 * sont relus en base, un prix modifié côté client est donc sans effet.
 */
class SaleService
{
    private const MAX_QUANTITY_PER_PRODUCT = 999;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @param array<int, mixed> $items ex. [['productId' => 1, 'quantity' => 2], ...]
     *
     * @throws \InvalidArgumentException si le panier est vide ou invalide
     */
    public function createSale(Event $event, array $items): Sale
    {
        $quantities = $this->normalizeItems($items);

        $products = $this->productRepository->findBy([
            'id' => array_keys($quantities),
            'active' => true,
        ]);

        if (count($products) !== count($quantities)) {
            throw new \InvalidArgumentException('Un article du panier est introuvable ou désactivé.');
        }

        $sale = new Sale($event, $this->generateReference());

        foreach ($products as $product) {
            $sale->addLine(new SaleLine(
                $product->getReference(),
                $product->getName(),
                $quantities[$product->getId()],
                $product->getPrice(),
                $product,
            ));
        }

        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    /**
     * Valide le contenu reçu et regroupe les quantités par article.
     *
     * @param array<int, mixed> $items
     *
     * @return array<int, int> [productId => quantity]
     */
    private function normalizeItems(array $items): array
    {
        if ([] === $items) {
            throw new \InvalidArgumentException('Le panier est vide.');
        }

        $quantities = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Panier invalide.');
            }

            $productId = filter_var($item['productId'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if (false === $productId || false === $quantity
                || $productId < 1 || $quantity < 1 || $quantity > self::MAX_QUANTITY_PER_PRODUCT) {
                throw new \InvalidArgumentException('Panier invalide.');
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        return $quantities;
    }

    /** Même format que l'ancien id_panier : "C" + horodatage en base 36 + 3 caractères aléatoires. */
    private function generateReference(): string
    {
        $timestamp = strtoupper(base_convert((string) (int) round(microtime(true) * 1000), 10, 36));
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));

        return 'C' . $timestamp . $random;
    }
}
