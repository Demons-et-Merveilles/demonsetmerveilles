<?php

namespace App\Controller\Jdr;

use App\Service\Jdr\JdrBoardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// À adapter : rôle autorisé à gérer les tables de JDR
#[Route('/admin/jdr', name: 'jdr_')]
#[IsGranted('ROLE_USER')]
class JdrController extends AbstractController
{
    public function __construct(private readonly JdrBoardService $board)
    {
    }

    /** Page du plateau : l'état initial est injecté dans la page, le JS prend ensuite le relais. */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('jdr/index.html.twig', [
            'state' => $this->board->getState(),
        ]);
    }

    /** État complet (sert au JS pour se resynchroniser avec le serveur). */
    #[Route('/state', name: 'state', methods: ['GET'])]
    public function state(): JsonResponse
    {
        return $this->json(['state' => $this->board->getState()]);
    }

    #[Route('/players', name: 'players_add', methods: ['POST'])]
    public function addPlayers(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->addPlayers((string) ($data['names'] ?? '')));
    }

    #[Route('/game-masters', name: 'game_masters_add', methods: ['POST'])]
    public function addGameMaster(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->addGameMaster());
    }

    #[Route('/tables', name: 'tables_add', methods: ['POST'])]
    public function addTable(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->addTable());
    }

    /** Glisser-déposer : { kind: 'gm'|'player', id, zone, table } */
    #[Route('/move', name: 'move', methods: ['POST'])]
    public function move(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->move(
            (string) ($data['kind'] ?? ''),
            (int) ($data['id'] ?? 0),
            (string) ($data['zone'] ?? ''),
            isset($data['table']) ? (int) $data['table'] : null,
        ));
    }

    #[Route('/rename', name: 'rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->rename(
            (string) ($data['kind'] ?? ''),
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
        ));
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->delete(
            (string) ($data['kind'] ?? ''),
            (int) ($data['id'] ?? 0),
        ));
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->board->clearTables());
    }

    /**
     * Point d'entrée commun des actions : vérifie le jeton CSRF, lit le JSON reçu,
     * exécute l'action puis renvoie l'état complet du plateau.
     *
     * L'action peut renvoyer false pour signaler un refus (ex. table pleine) : "ok" vaut alors false.
     */
    private function apply(Request $request, callable $action): JsonResponse
    {
        if (!$this->isCsrfTokenValid('jdr', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = []; // certaines actions n'ont pas de corps
        }

        try {
            $result = $action($data);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
                'state' => $this->board->getState(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'ok' => false !== $result,
            'state' => $this->board->getState(),
        ]);
    }
}
