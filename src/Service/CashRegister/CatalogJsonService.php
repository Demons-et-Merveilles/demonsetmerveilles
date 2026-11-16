<?php

namespace App\Service\CashRegister;

use App\Entity\CashRegister\Category;
use App\Entity\CashRegister\Product;
use App\Repository\CashRegister\CategoryRepository;
use App\Repository\CashRegister\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import / export du catalogue au format historique de products.json :
 * [{ "id": "P001", "nom": "Boisson", "prix": 1.5, "categorie": "Snack", "emoji": "🥛" }]
 */
class CatalogJsonService
{
    private const DEFAULT_CATEGORY = 'Divers';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function export(): array
    {
        $data = [];

        foreach ($this->productRepository->findBy([], ['reference' => 'ASC']) as $product) {
            $data[] = [
                'id' => $product->getReference(),
                'nom' => $product->getName(),
                'prix' => (float) $product->getPrice(),
                'categorie' => $product->getCategory()?->getName(),
                'emoji' => $product->getEmoji(),
            ];
        }

        return $data;
    }

    /**
     * Crée ou met à jour les articles (clé = "id" du JSON, stocké dans Product::$reference).
     * Les articles absents du fichier ne sont jamais supprimés : l'historique des ventes reste intact.
     *
     * @param array<int, mixed> $rows
     *
     * @return int nombre d'articles traités
     *
     * @throws \InvalidArgumentException si une ligne est invalide (rien n'est alors enregistré)
     */
    public function import(array $rows): int
    {
        $this->validate($rows);

        $categoriesByName = [];

        foreach ($rows as $row) {
            $reference = (string) $row['id'];

            $product = $this->productRepository->findOneBy(['reference' => $reference]) ?? new Product();
            $product
                ->setReference($reference)
                ->setName(trim((string) $row['nom']))
                ->setPrice($row['prix'])
                ->setEmoji($this->cleanEmoji($row['emoji'] ?? null))
                ->setCategory($this->findOrCreateCategory((string) ($row['categorie'] ?? ''), $categoriesByName))
                ->setActive(true);

            $this->em->persist($product);
        }

        $this->em->flush();

        return count($rows);
    }

    /** @param array<int, mixed> $rows */
    private function validate(array $rows): void
    {
        $seenReferences = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;

            if (!is_array($row)
                || !isset($row['id'], $row['nom'], $row['prix'])
                || '' === trim((string) $row['id'])
                || '' === trim((string) $row['nom'])
                || !is_numeric($row['prix'])
                || $row['prix'] < 0) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : champ manquant ou invalide (id, nom, prix).', $line));
            }

            $reference = (string) $row['id'];
            if (isset($seenReferences[$reference])) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : id "%s" en double.', $line, $reference));
            }
            $seenReferences[$reference] = true;
        }
    }

    /** @param array<string, Category> $cache catégories déjà vues pendant cet import */
    private function findOrCreateCategory(string $name, array &$cache): Category
    {
        $name = trim($name);
        if ('' === $name) {
            $name = self::DEFAULT_CATEGORY;
        }

        if (!isset($cache[$name])) {
            $category = $this->categoryRepository->findOneBy(['name' => $name]);

            if (null === $category) {
                $category = (new Category())->setName($name);
                $this->em->persist($category);
            }

            $cache[$name] = $category;
        }

        return $cache[$name];
    }

    private function cleanEmoji(mixed $emoji): ?string
    {
        $emoji = trim((string) $emoji);

        return '' === $emoji ? null : $emoji;
    }
}
