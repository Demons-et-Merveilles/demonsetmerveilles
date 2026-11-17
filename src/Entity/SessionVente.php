<?php

namespace App\Entity;

use App\Repository\SessionVenteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Session de vente (ex : "Samedi après-midi") rattachée à un évènement.
 * C'est sur une session ouverte que la caisse enregistre les ventes.
 */
#[ORM\Entity(repositoryClass: SessionVenteRepository::class)]
#[ORM\Table(name: 'session_vente')]
class SessionVente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Evenement $evenement;

    #[ORM\Column(length: 150)]
    private string $nom;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $ouverteLe;

    // Renseignée = session archivée (plus de vente possible, consultation seulement)
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archiveeLe = null;

    /** @var Collection<int, Vente> */
    #[ORM\OneToMany(targetEntity: Vente::class, mappedBy: 'session')]
    private Collection $ventes;

    public function __construct(Evenement $evenement, string $nom)
    {
        $this->evenement = $evenement;
        $this->nom = $nom;
        $this->ouverteLe = new \DateTimeImmutable();
        $this->ventes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): Evenement
    {
        return $this->evenement;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getOuverteLe(): \DateTimeImmutable
    {
        return $this->ouverteLe;
    }

    public function getArchiveeLe(): ?\DateTimeImmutable
    {
        return $this->archiveeLe;
    }

    public function estArchivee(): bool
    {
        return $this->archiveeLe !== null;
    }

    public function archiver(): static
    {
        $this->archiveeLe = new \DateTimeImmutable();

        return $this;
    }

    public function desarchiver(): static
    {
        $this->archiveeLe = null;

        return $this;
    }

    /**
     * Une session est utilisable en caisse si elle n'est pas archivée
     * ET si son évènement ne l'est pas non plus.
     */
    public function estOuverte(): bool
    {
        return $this->archiveeLe === null && !$this->evenement->estArchive();
    }

    /** @return Collection<int, Vente> */
    public function getVentes(): Collection
    {
        return $this->ventes;
    }
}
