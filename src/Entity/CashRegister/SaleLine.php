<?php

namespace App\Entity\CashRegister;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne d'un panier (un article + sa quantité).
 *
 * Le nom, la référence et le prix sont copiés au moment de la vente :
 * l'historique reste exact même si l'article est modifié ou supprimé ensuite.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cash_register_sale_line')]
class SaleLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Sale $sale;

    // Lien vers l'article d'origine (null si l'article a été supprimé depuis)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(length: 20)]
    private string $productReference;

    #[ORM\Column(length: 150)]
    private string $productName;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subtotal;

    public function __construct(
        string $productReference,
        string $productName,
        int $quantity,
        string|float|int $unitPrice,
        ?Product $product = null,
    ) {
        $this->productReference = $productReference;
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPrice = number_format((float) $unitPrice, 2, '.', '');
        $this->subtotal = number_format($quantity * (float) $unitPrice, 2, '.', '');
        $this->product = $product;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    /** Appelé par Sale::addLine(), ne pas utiliser directement. */
    public function setSale(Sale $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getProductReference(): string
    {
        return $this->productReference;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }
}
