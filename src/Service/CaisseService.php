<?php

namespace App\Service;

use App\Entity\LigneVente;
use App\Entity\Produit;
use App\Entity\SessionVente;
use App\Entity\Vente;
use App\Repository\ProduitRepository;
use App\Repository\VenteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier de la caisse : catalogue, enregistrement des ventes, import / export JSON.
 * Les erreurs de saisie sont signalées par InvalidArgumentException / DomainException
 * (le contrôleur les transforme en réponse JSON).
 */
final class CaisseService
{
    // Format d'un identifiant de panier généré par la caisse (ex : CMH3K9XYZ)
    private const MOTIF_REFERENCE_PANIER = '/^[A-Z0-9]{6,40}$/';
    private const QUANTITE_MAX = 999;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProduitRepository $produits,
        private readonly VenteRepository $ventes,
    ) {
    }

    // ──────────────────────────────────────────────
    // CATALOGUE
    // ──────────────────────────────────────────────

    /**
     * Catalogue actif au format attendu par la caisse (même format que l'ancien products.json).
     *
     * @return list<array{id: string, nom: string, prix: float, categorie: string, emoji: ?string}>
     */
    public function catalogue(): array
    {
        return array_map(static fn (Produit $p): array => [
            'id' => $p->getReference(),
            'nom' => $p->getNom(),
            'prix' => (float) $p->getPrix(),
            'categorie' => $p->getCategorie(),
            'emoji' => $p->getEmoji(),
        ], $this->produits->actifs());
    }

    /**
     * Importe un catalogue JSON : les produits existants (même "id") sont mis à jour,
     * les nouveaux sont créés. Les produits absents du fichier ne sont pas touchés.
     *
     * @param array<int, mixed> $donnees
     * @return int nombre de produits importés
     */
    public function importerCatalogue(array $donnees): int
    {
        $donnees = array_values($donnees); // numérotation 0..n-1, même si le JSON était un objet

        // 1) On valide tout avant de modifier quoi que ce soit
        $references = [];
        foreach ($donnees as $i => $ligne) {
            $numero = $i + 1;
            if (!is_array($ligne) || !isset($ligne['id'], $ligne['nom'], $ligne['prix']) || !is_numeric($ligne['prix']) || $ligne['prix'] < 0) {
                throw new \InvalidArgumentException(sprintf('Ligne %d invalide : "id", "nom" et "prix" sont obligatoires.', $numero));
            }
            $references[] = $this->texte($ligne['id'], 20, 'id', $numero);
        }

        // 2) Création ou mise à jour
        $existants = $this->produits->indexParReference($references);
        foreach ($donnees as $i => $ligne) {
            $numero = $i + 1;
            $reference = $this->texte($ligne['id'], 20, 'id', $numero);

            $produit = $existants[$reference] ?? null;
            if ($produit === null) {
                $produit = (new Produit())->setReference($reference);
                $this->em->persist($produit);
                $existants[$reference] = $produit; // évite un doublon si l'id est répété dans le fichier
            }

            $produit
                ->setNom($this->texte($ligne['nom'], 120, 'nom', $numero))
                ->setPrix(self::enMontant(self::enCentimes($ligne['prix'])))
                ->setCategorie($this->texte($ligne['categorie'] ?? 'Divers', 60, 'categorie', $numero))
                ->setEmoji(isset($ligne['emoji']) && $ligne['emoji'] !== '' ? $this->texte($ligne['emoji'], 16, 'emoji', $numero) : null)
                ->setActif(true);
        }

        $this->em->flush();

        return count($donnees);
    }

    // ──────────────────────────────────────────────
    // VENTES
    // ──────────────────────────────────────────────

    /**
     * Enregistre un panier dans une session. Les prix sont relus en base :
     * on ne fait jamais confiance aux montants envoyés par le navigateur.
     *
     * @param array<string|int, mixed> $panier référence produit => quantité
     */
    public function enregistrerVente(SessionVente $session, string $reference, array $panier): Vente
    {
        if (!$session->estOuverte()) {
            throw new \DomainException('Cette session est archivée : plus aucune vente possible.');
        }
        if (!preg_match(self::MOTIF_REFERENCE_PANIER, $reference)) {
            throw new \InvalidArgumentException('Identifiant de panier invalide.');
        }
        if ($panier === []) {
            throw new \InvalidArgumentException('Le panier est vide.');
        }
        if ($this->ventes->findOneBy(['reference' => $reference]) !== null) {
            throw new \DomainException('Ce panier a déjà été enregistré.');
        }

        $references = array_map('strval', array_keys($panier));
        $produits = $this->produits->indexParReference($references);

        $vente = new Vente($session, $reference);
        foreach ($panier as $referenceProduit => $quantite) {
            $referenceProduit = (string) $referenceProduit;
            $produit = $produits[$referenceProduit] ?? null;

            if ($produit === null || !$produit->estActif()) {
                throw new \InvalidArgumentException(sprintf('Article inconnu ou désactivé : %s.', $referenceProduit));
            }
            if (!is_int($quantite) || $quantite < 1 || $quantite > self::QUANTITE_MAX) {
                throw new \InvalidArgumentException(sprintf('Quantité invalide pour %s.', $referenceProduit));
            }

            $vente->ajouterLigne($this->creerLigne($produit, $quantite));
        }

        try {
            $this->em->persist($vente);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Double clic : le même panier a été envoyé deux fois
            throw new \DomainException('Ce panier a déjà été enregistré.');
        }

        return $vente;
    }

    private function creerLigne(Produit $produit, int $quantite): LigneVente
    {
        $prixCentimes = self::enCentimes($produit->getPrix());

        return (new LigneVente())
            ->setProduit($produit)
            ->setProduitReference($produit->getReference())
            ->setProduitNom($produit->getNom())
            ->setPrixUnitaire(self::enMontant($prixCentimes))
            ->setQuantite($quantite)
            ->setSousTotal(self::enMontant($prixCentimes * $quantite));
    }

    // ──────────────────────────────────────────────
    // HISTORIQUE (format identique à l'ancien historique_ventes.json)
    // ──────────────────────────────────────────────

    /**
     * Historique d'une session, une entrée par ligne de vente, de la plus ancienne à la plus récente.
     *
     * @return list<array<string, mixed>>
     */
    public function historique(SessionVente $session): array
    {
        $historique = [];
        foreach ($this->ventes->ventesDeSession($session) as $vente) {
            foreach ($vente->getLignes() as $ligne) {
                $historique[] = [
                    'id_panier' => $vente->getReference(),
                    'id_article' => $ligne->getProduitReference(),
                    'nom' => $ligne->getProduitNom(),
                    'quantite' => $ligne->getQuantite(),
                    'prix_unitaire' => (float) $ligne->getPrixUnitaire(),
                    'sous_total' => (float) $ligne->getSousTotal(),
                    'date' => $vente->getDateVente()->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        return $historique;
    }

    /**
     * Importe un ancien historique JSON dans une session ouverte.
     * Les paniers déjà présents en base (même "id_panier") sont ignorés.
     *
     * @param array<int, mixed> $donnees
     * @return int nombre de paniers importés
     */
    public function importerHistorique(SessionVente $session, array $donnees): int
    {
        if (!$session->estOuverte()) {
            throw new \DomainException('Cette session est archivée : import impossible.');
        }

        $donnees = array_values($donnees); // numérotation 0..n-1, même si le JSON était un objet

        // Regroupe les lignes par panier, en validant au passage
        $paniers = [];
        $references = [];
        foreach ($donnees as $i => $ligne) {
            $numero = $i + 1;
            if (
                !is_array($ligne)
                || !isset($ligne['id_panier'], $ligne['id_article'], $ligne['nom'], $ligne['quantite'], $ligne['prix_unitaire'])
                || !is_numeric($ligne['quantite']) || (int) $ligne['quantite'] < 1
                || !is_numeric($ligne['prix_unitaire'])
            ) {
                throw new \InvalidArgumentException(sprintf('Entrée %d invalide dans l\'historique.', $numero));
            }
            $idPanier = (string) $ligne['id_panier'];
            if (!preg_match(self::MOTIF_REFERENCE_PANIER, $idPanier)) {
                throw new \InvalidArgumentException(sprintf('Entrée %d : identifiant de panier invalide.', $numero));
            }
            $paniers[$idPanier][] = $ligne;
            $references[] = (string) $ligne['id_article'];
        }

        $produits = $this->produits->indexParReference(array_values(array_unique($references)));

        $importes = 0;
        foreach ($paniers as $idPanier => $lignes) {
            if ($this->ventes->findOneBy(['reference' => $idPanier]) !== null) {
                continue; // déjà importé
            }

            try {
                $date = new \DateTimeImmutable((string) ($lignes[0]['date'] ?? 'now'));
            } catch (\Exception) {
                $date = new \DateTimeImmutable();
            }

            $vente = new Vente($session, (string) $idPanier, $date);
            foreach ($lignes as $ligne) {
                $quantite = (int) $ligne['quantite'];
                $prixCentimes = self::enCentimes($ligne['prix_unitaire']);

                $vente->ajouterLigne(
                    (new LigneVente())
                        ->setProduit($produits[(string) $ligne['id_article']] ?? null)
                        ->setProduitReference($this->texte($ligne['id_article'], 20, 'id_article', 0))
                        ->setProduitNom($this->texte($ligne['nom'], 120, 'nom', 0))
                        ->setPrixUnitaire(self::enMontant($prixCentimes))
                        ->setQuantite($quantite)
                        ->setSousTotal(self::enMontant($prixCentimes * $quantite))
                );
            }

            $this->em->persist($vente);
            ++$importes;
        }

        $this->em->flush();

        return $importes;
    }

    // ──────────────────────────────────────────────
    // OUTILS
    // ──────────────────────────────────────────────

    // Convertit un prix en centimes entiers (évite les erreurs d'arrondi des flottants)
    private static function enCentimes(string|int|float $prix): int
    {
        return (int) round(((float) $prix) * 100);
    }

    // Convertit des centimes en montant décimal "12.50" pour la base
    private static function enMontant(int $centimes): string
    {
        return number_format($centimes / 100, 2, '.', '');
    }

    // Vérifie qu'une valeur est un texte non vide de longueur raisonnable
    private function texte(mixed $valeur, int $longueurMax, string $champ, int $numeroLigne): string
    {
        $texte = trim((string) $valeur);
        if ($texte === '' || mb_strlen($texte) > $longueurMax) {
            throw new \InvalidArgumentException(sprintf('Ligne %d : "%s" vide ou trop long (%d caractères max).', $numeroLigne, $champ, $longueurMax));
        }

        return $texte;
    }
}
