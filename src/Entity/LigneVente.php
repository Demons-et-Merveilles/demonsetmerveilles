<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'une vente. Le nom et le prix sont copiés au moment de la vente :
 * si le catalogue change plus tard, l'historique reste exact.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ligne_vente')]
class LigneVente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vente::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vente $vente;

    // Lien vers le produit (peut devenir null si le produit est supprimé un jour)
    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Produit $produit = null;

    // Copies figées au moment de la vente
    #[ORM\Column(length: 20)]
    private string $produitReference = '';

    #[ORM\Column(length: 120)]
    private string $produitNom = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixUnitaire = '0.00';

    #[ORM\Column]
    private int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $sousTotal = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVente(): Vente
    {
        return $this->vente;
    }

    public function setVente(Vente $vente): static
    {
        $this->vente = $vente;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getProduitReference(): string
    {
        return $this->produitReference;
    }

    public function setProduitReference(string $produitReference): static
    {
        $this->produitReference = $produitReference;

        return $this;
    }

    public function getProduitNom(): string
    {
        return $this->produitNom;
    }

    public function setProduitNom(string $produitNom): static
    {
        $this->produitNom = $produitNom;

        return $this;
    }

    public function getPrixUnitaire(): string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getSousTotal(): string
    {
        return $this->sousTotal;
    }

    public function setSousTotal(string $sousTotal): static
    {
        $this->sousTotal = $sousTotal;

        return $this;
    }
}
