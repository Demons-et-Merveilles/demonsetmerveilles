<?php

namespace App\Entity\CashRegister;

use App\Repository\CashRegister\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Évènement qui regroupe les ventes (ex. « Convention 2026 »).
 * Chaque vente (Sale) appartient à un évènement.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'cash_register_event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Sale> */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Sale::class, cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $sales;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->sales = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Sale> */
    public function getSales(): Collection
    {
        return $this->sales;
    }

    public function getSalesCount(): int
    {
        return $this->sales->count();
    }

    /** Chiffre d'affaires de l'évènement (somme des paniers). */
    public function getTotal(): string
    {
        $total = 0.0;
        foreach ($this->sales as $sale) {
            $total += (float) $sale->getTotal();
        }

        return number_format($total, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
