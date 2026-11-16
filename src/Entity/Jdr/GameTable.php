<?php

namespace App\Entity\Jdr;

use App\Repository\Jdr\GameTableRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Table de jeu de rôle : un MJ (optionnel) et jusqu'à 6 joueurs.
 * Les joueurs pointent vers leur table (voir Player::$gameTable).
 */
#[ORM\Entity(repositoryClass: GameTableRepository::class)]
#[ORM\Table(name: 'jdr_game_table')]
class GameTable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name = '';

    // MJ qui anime la table (null = pas de MJ). La règle « un MJ = une seule table » est gérée par JdrBoardService.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GameMaster $gameMaster = null;

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

    public function getGameMaster(): ?GameMaster
    {
        return $this->gameMaster;
    }

    public function setGameMaster(?GameMaster $gameMaster): static
    {
        $this->gameMaster = $gameMaster;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
