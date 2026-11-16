<?php

namespace App\Entity\Jdr;

use App\Repository\Jdr\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Joueur. Sans table, il est dans la liste « Joueurs » ; avec une table, il est assis à cette table.
 */
#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'jdr_player')]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GameTable $gameTable = null;

    // Ordre d'arrivée à la table (les joueurs s'affichent dans l'ordre où ils ont été déposés)
    #[ORM\Column]
    private int $seatOrder = 0;

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

    public function getGameTable(): ?GameTable
    {
        return $this->gameTable;
    }

    public function setGameTable(?GameTable $gameTable): static
    {
        $this->gameTable = $gameTable;

        // Un joueur qui quitte sa table n'a plus de place
        if (null === $gameTable) {
            $this->seatOrder = 0;
        }

        return $this;
    }

    public function getSeatOrder(): int
    {
        return $this->seatOrder;
    }

    public function setSeatOrder(int $seatOrder): static
    {
        $this->seatOrder = $seatOrder;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
