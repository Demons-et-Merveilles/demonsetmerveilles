<?php

namespace App\Controller\Admin;

use App\Entity\SessionVente;
use App\Repository\SessionVenteRepository;
use App\Service\CaisseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Caisse : page de vente + petite API JSON appelée par public/js/caisse.js (jQuery).
 * Protégé par access_control (^/admin) dans security.yaml.
 */
#[Route('/admin/caisse')]
final class CaisseController extends AbstractController
{
    // Identifiant du jeton CSRF des appels AJAX (envoyé dans l'en-tête X-CSRF-Token)
    private const JETON_CSRF = 'caisse';

    // ──────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────

    // Point d'entrée : redirige vers la session ouverte, ou propose le choix
    #[Route('', name: 'app_caisse', methods: ['GET'])]
    public function index(SessionVenteRepository $sessions): Response
    {
        $ouvertes = $sessions->ouvertes();

        if ($ouvertes === []) {
            $this->addFlash('avertissement', 'Aucune session de vente ouverte : ouvrez-en une sur un évènement pour utiliser la caisse.');

            return $this->redirectToRoute('app_evenements');
        }

        if (count($ouvertes) === 1) {
            return $this->redirectToRoute('app_caisse_session', ['id' => $ouvertes[0]->getId()]);
        }

        return $this->render('admin/caisse/choix.html.twig', ['sessions' => $ouvertes]);
    }

    #[Route('/session/{id}', name: 'app_caisse_session', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function session(SessionVente $session): Response
    {
        if (!$session->estOuverte()) {
            $this->addFlash('avertissement', 'Cette session est archivée : la caisse est fermée, consultation seulement.');

            return $this->redirectToRoute('app_session_detail', ['id' => $session->getId()]);
        }

        // Le jeton CSRF des appels AJAX est généré dans le template : csrf_token('caisse')
        return $this->render('admin/caisse/index.html.twig', ['session' => $session]);
    }

    // ──────────────────────────────────────────────
    // API CATALOGUE
    // ──────────────────────────────────────────────

    #[Route('/produits', name: 'app_caisse_produits', methods: ['GET'])]
    public function produits(CaisseService $caisse): JsonResponse
    {
        return $this->json($caisse->catalogue());
    }

    #[Route('/produits/export', name: 'app_caisse_produits_export', methods: ['GET'])]
    public function exporterProduits(CaisseService $caisse): JsonResponse
    {
        return $this->telechargerJson($caisse->catalogue(), 'products.json');
    }

    #[Route('/produits/import', name: 'app_caisse_produits_import', methods: ['POST'])]
    public function importerProduits(Request $request, CaisseService $caisse): JsonResponse
    {
        $this->verifierCsrf($request);

        try {
            $importes = $caisse->importerCatalogue($this->lireJson($request));
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['ok' => true, 'importes' => $importes]);
    }

    // ──────────────────────────────────────────────
    // API VENTES / HISTORIQUE
    // ──────────────────────────────────────────────

    // Corps attendu : { "idPanier": "C123ABC", "panier": { "P001": 2, "P003": 1 } }
    #[Route('/session/{id}/vente', name: 'app_caisse_vente', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function vente(SessionVente $session, Request $request, CaisseService $caisse): JsonResponse
    {
        $this->verifierCsrf($request);

        try {
            $donnees = $this->lireJson($request);
            $vente = $caisse->enregistrerVente(
                $session,
                (string) ($donnees['idPanier'] ?? ''),
                is_array($donnees['panier'] ?? null) ? $donnees['panier'] : [],
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['ok' => true, 'total' => $vente->getTotal()], Response::HTTP_CREATED);
    }

    #[Route('/session/{id}/historique', name: 'app_caisse_historique', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function historique(SessionVente $session, CaisseService $caisse): JsonResponse
    {
        return $this->json($caisse->historique($session));
    }

    #[Route('/session/{id}/historique/export', name: 'app_caisse_historique_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exporterHistorique(SessionVente $session, CaisseService $caisse): JsonResponse
    {
        return $this->telechargerJson($caisse->historique($session), 'historique_ventes.json');
    }

    #[Route('/session/{id}/historique/import', name: 'app_caisse_historique_import', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function importerHistorique(SessionVente $session, Request $request, CaisseService $caisse): JsonResponse
    {
        $this->verifierCsrf($request);

        try {
            $importes = $caisse->importerHistorique($session, $this->lireJson($request));
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['ok' => true, 'importes' => $importes]);
    }

    // ──────────────────────────────────────────────
    // OUTILS
    // ──────────────────────────────────────────────

    // Vérifie le jeton CSRF envoyé par jQuery dans l'en-tête X-CSRF-Token
    private function verifierCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::JETON_CSRF, (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /** @return array<mixed> */
    private function lireJson(Request $request): array
    {
        try {
            $donnees = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('JSON invalide.');
        }

        if (!is_array($donnees)) {
            throw new \InvalidArgumentException('JSON invalide : un tableau ou un objet est attendu.');
        }

        return $donnees;
    }

    // Réponse JSON indentée, téléchargée comme fichier
    private function telechargerJson(array $donnees, string $nomFichier): JsonResponse
    {
        $reponse = new JsonResponse($donnees);
        $reponse->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $reponse->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomFichier),
        );

        return $reponse;
    }
}
