<?php

namespace App\Entity\CashRegister;

use App\Repository\CashRegister\SaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une vente = un panier encaissé pendant un évènement.
 * Remplace l'ancien "id_panier" de historique_ventes.json.
 */
#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: 'cash_register_sale')]
class Sale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sales')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    // Identifiant lisible du panier (ex. "CMK3J9XA1B2")
    #[ORM\Column(length: 30, unique: true)]
    private string $reference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    /** @var Collection<int, SaleLine> */
    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: SaleLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lines;

    public function __construct(Event $event, string $reference, ?\DateTimeImmutable $createdAt = null)
    {
        $this->event = $event;
        $this->reference = $reference;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    /** @return Collection<int, SaleLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(SaleLine $line): static
    {
        $this->lines->add($line);
        $line->setSale($this);
        $this->recalculateTotal();

        return $this;
    }

    /** Le total est toujours la somme des sous-totaux des lignes. */
    private function recalculateTotal(): void
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += (float) $line->getSubtotal();
        }

        $this->total = number_format($total, 2, '.', '');
    }
}
