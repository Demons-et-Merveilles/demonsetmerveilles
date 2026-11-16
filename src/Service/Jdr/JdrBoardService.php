<?php

namespace App\Service\Jdr;

use App\Entity\Jdr\GameMaster;
use App\Entity\Jdr\GameTable;
use App\Entity\Jdr\Player;
use App\Repository\Jdr\GameMasterRepository;
use App\Repository\Jdr\GameTableRepository;
use App\Repository\Jdr\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Toute la logique du plateau de JDR (ce que faisait l'ancien app.js dans le navigateur).
 *
 * Vocabulaire des « kinds » échangés avec le JS : 'gm' (MJ), 'player' (joueur), 'table'.
 * Vocabulaire des « zones » de dépôt : 'pool-gms', 'pool-players', 'table-gm', 'table-players'.
 */
class JdrBoardService
{
    public const MAX_PLAYERS = 6;     // joueurs max par table
    public const MAX_NAME_LENGTH = 30;

    private const DEFAULT_GAME_MASTERS = 6;
    private const DEFAULT_TABLES = 4;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameMasterRepository $gameMasterRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly GameTableRepository $gameTableRepository,
    ) {
    }

    // ──────────────────────────────────────────────
    // LECTURE
    // ──────────────────────────────────────────────

    /**
     * État complet du plateau, sous la forme attendue par le JS :
     * gms / players : [{id, name}] ; tables : [{id, name, gm: id|null, players: [id]}].
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $players = $this->playerRepository->findBy([], ['id' => 'ASC']);

        // Joueurs assis, regroupés par table
        $seatedByTable = [];
        foreach ($players as $player) {
            $table = $player->getGameTable();
            if (null !== $table) {
                $seatedByTable[$table->getId()][] = $player;
            }
        }

        $tables = [];
        foreach ($this->gameTableRepository->findBy([], ['id' => 'ASC']) as $table) {
            $seated = $seatedByTable[$table->getId()] ?? [];
            // Dans l'ordre où les joueurs se sont assis
            usort($seated, static fn (Player $a, Player $b) => [$a->getSeatOrder(), $a->getId()] <=> [$b->getSeatOrder(), $b->getId()]);

            $tables[] = [
                'id' => $table->getId(),
                'name' => $table->getName(),
                'gm' => $table->getGameMaster()?->getId(),
                'players' => array_map(static fn (Player $player) => $player->getId(), $seated),
            ];
        }

        return [
            'maxPlayers' => self::MAX_PLAYERS,
            'gms' => array_map(
                static fn (GameMaster $gm) => ['id' => $gm->getId(), 'name' => $gm->getName()],
                $this->gameMasterRepository->findBy([], ['id' => 'ASC'])
            ),
            'players' => array_map(
                static fn (Player $player) => ['id' => $player->getId(), 'name' => $player->getName()],
                $players
            ),
            'tables' => $tables,
        ];
    }

    // ──────────────────────────────────────────────
    // INITIALISATION
    // ──────────────────────────────────────────────

    /**
     * Crée les MJ (MJ1…MJ6) et les tables (Table 1…Table 4) par défaut.
     *
     * @return bool false si le plateau contient déjà des données (rien n'est alors créé)
     */
    public function initializeDefaults(): bool
    {
        if (0 !== $this->gameMasterRepository->count([])
            || 0 !== $this->gameTableRepository->count([])
            || 0 !== $this->playerRepository->count([])) {
            return false;
        }

        for ($i = 1; $i <= self::DEFAULT_GAME_MASTERS; ++$i) {
            $this->em->persist((new GameMaster())->setName('MJ' . $i));
        }
        for ($i = 1; $i <= self::DEFAULT_TABLES; ++$i) {
            $this->em->persist((new GameTable())->setName('Table ' . $i));
        }
        $this->em->flush();

        return true;
    }

    // ──────────────────────────────────────────────
    // AJOUTS
    // ──────────────────────────────────────────────

    /** Ajoute un ou plusieurs joueurs : les noms sont séparés par une virgule, un point-virgule ou un retour à la ligne. */
    public function addPlayers(string $rawNames): void
    {
        foreach (preg_split('/[,;\n]+/', $rawNames) ?: [] as $rawName) {
            $name = trim($rawName);
            if ('' !== $name) {
                $this->em->persist((new Player())->setName(mb_substr($name, 0, self::MAX_NAME_LENGTH)));
            }
        }

        $this->em->flush();
    }

    public function addGameMaster(): void
    {
        $count = $this->gameMasterRepository->count([]);
        $this->em->persist((new GameMaster())->setName('MJ' . ($count + 1)));
        $this->em->flush();
    }

    public function addTable(): void
    {
        $count = $this->gameTableRepository->count([]);
        $this->em->persist((new GameTable())->setName('Table ' . ($count + 1)));
        $this->em->flush();
    }

    // ──────────────────────────────────────────────
    // DÉPLACEMENTS
    // ──────────────────────────────────────────────

    /**
     * Applique un glisser-déposer.
     *
     * @return bool false si le dépôt est impossible (table pleine, mauvaise zone…)
     *
     * @throws \InvalidArgumentException si l'élément ou la table n'existe pas
     */
    public function move(string $kind, int $id, string $zone, ?int $tableId): bool
    {
        $moved = match ($kind) {
            'player' => $this->movePlayer($this->findPlayer($id), $zone, $tableId),
            'gm' => $this->moveGameMaster($this->findGameMaster($id), $zone, $tableId),
            default => throw new \InvalidArgumentException('Type d\'élément invalide.'),
        };

        $this->em->flush();

        return $moved;
    }

    private function movePlayer(Player $player, string $zone, ?int $tableId): bool
    {
        if ('pool-players' === $zone) {
            $player->setGameTable(null);

            return true;
        }
        if ('table-players' !== $zone) {
            return false;
        }

        $table = $this->findTable($tableId);

        if ($player->getGameTable()?->getId() === $table->getId()) {
            return true; // déjà à cette table
        }
        if ($this->playerRepository->count(['gameTable' => $table]) >= self::MAX_PLAYERS) {
            return false; // table pleine
        }

        $player->setGameTable($table);
        $player->setSeatOrder($this->playerRepository->findMaxSeatOrder($table) + 1);

        return true;
    }

    private function moveGameMaster(GameMaster $gameMaster, string $zone, ?int $tableId): bool
    {
        if ('pool-gms' === $zone) {
            $this->detachGameMaster($gameMaster);

            return true;
        }
        if ('table-gm' !== $zone) {
            return false;
        }

        $table = $this->findTable($tableId);
        $this->detachGameMaster($gameMaster);
        $table->setGameMaster($gameMaster); // l'ancien MJ de la table retourne automatiquement dans la liste

        return true;
    }

    /** Retire un MJ de toutes les tables (il retourne dans la liste des MJ). */
    private function detachGameMaster(GameMaster $gameMaster): void
    {
        foreach ($this->gameTableRepository->findBy(['gameMaster' => $gameMaster]) as $table) {
            $table->setGameMaster(null);
        }
    }

    /** Remet tout le monde dans les listes, sans rien supprimer. */
    public function clearTables(): void
    {
        foreach ($this->gameTableRepository->findAll() as $table) {
            $table->setGameMaster(null);
        }
        foreach ($this->playerRepository->findAll() as $player) {
            $player->setGameTable(null);
        }

        $this->em->flush();
    }

    // ──────────────────────────────────────────────
    // RENOMMER / SUPPRIMER
    // ──────────────────────────────────────────────

    /** @throws \InvalidArgumentException si le nom est vide ou l'élément introuvable */
    public function rename(string $kind, int $id, string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Le nom ne peut pas être vide.');
        }

        $this->findItem($kind, $id)->setName(mb_substr($name, 0, self::MAX_NAME_LENGTH));
        $this->em->flush();
    }

    /** @throws \InvalidArgumentException si l'élément est introuvable */
    public function delete(string $kind, int $id): void
    {
        $item = $this->findItem($kind, $id);

        if ($item instanceof GameMaster) {
            $this->detachGameMaster($item);
        }
        if ($item instanceof GameTable) {
            // Les joueurs de la table retournent dans la liste
            foreach ($this->playerRepository->findBy(['gameTable' => $item]) as $player) {
                $player->setGameTable(null);
            }
        }

        $this->em->remove($item);
        $this->em->flush();
    }

    // ──────────────────────────────────────────────
    // RECHERCHES
    // ──────────────────────────────────────────────

    private function findItem(string $kind, int $id): GameMaster|Player|GameTable
    {
        return match ($kind) {
            'gm' => $this->findGameMaster($id),
            'player' => $this->findPlayer($id),
            'table' => $this->findTable($id),
            default => throw new \InvalidArgumentException('Type d\'élément invalide.'),
        };
    }

    private function findGameMaster(int $id): GameMaster
    {
        return $this->gameMasterRepository->find($id) ?? throw new \InvalidArgumentException('MJ introuvable.');
    }

    private function findPlayer(int $id): Player
    {
        return $this->playerRepository->find($id) ?? throw new \InvalidArgumentException('Joueur introuvable.');
    }

    private function findTable(?int $id): GameTable
    {
        if (null === $id) {
            throw new \InvalidArgumentException('Table manquante.');
        }

        return $this->gameTableRepository->find($id) ?? throw new \InvalidArgumentException('Table introuvable.');
    }
}
