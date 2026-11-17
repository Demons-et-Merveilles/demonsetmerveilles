<?php

namespace App\Entity;

use App\Repository\VenteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un panier encaissé (une "vente"), composé de plusieurs lignes.
 */
#[ORM\Entity(repositoryClass: VenteRepository::class)]
#[ORM\Table(name: 'vente')]
class Vente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SessionVente::class, inversedBy: 'ventes')]
    #[ORM\JoinColumn(nullable: false)]
    private SessionVente $session;

    // Identifiant du panier généré par la caisse (ex : CMH3K9XYZ) ; unique, évite les doublons
    #[ORM\Column(length: 40, unique: true)]
    private string $reference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateVente;

    /** @var Collection<int, LigneVente> */
    #[ORM\OneToMany(targetEntity: LigneVente::class, mappedBy: 'vente', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lignes;

    public function __construct(SessionVente $session, string $reference, ?\DateTimeImmutable $dateVente = null)
    {
        $this->session = $session;
        $this->reference = $reference;
        $this->dateVente = $dateVente ?? new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): SessionVente
    {
        return $this->session;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getDateVente(): \DateTimeImmutable
    {
        return $this->dateVente;
    }

    /** @return Collection<int, LigneVente> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function ajouterLigne(LigneVente $ligne): static
    {
        $ligne->setVente($this);
        $this->lignes->add($ligne);

        return $this;
    }

    // Total du panier (somme des sous-totaux des lignes)
    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->lignes as $ligne) {
            $total += (float) $ligne->getSousTotal();
        }

        return round($total, 2);
    }
}
