<?php

namespace App\Entity\Jdr;

use App\Repository\Jdr\GameMasterRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maître de jeu (MJ1, MJ2…). Un MJ est « placé » quand une table le référence.
 */
#[ORM\Entity(repositoryClass: GameMasterRepository::class)]
#[ORM\Table(name: 'jdr_game_master')]
class GameMaster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name = '';

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

    public function __toString(): string
    {
        return $this->name;
    }
}
