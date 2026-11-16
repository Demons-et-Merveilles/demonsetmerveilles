<?php

namespace App\Controller\CashRegister;

use App\Entity\CashRegister\Event;
use App\Service\CashRegister\CatalogJsonService;
use App\Service\CashRegister\SalesHistoryJsonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Imports / exports JSON (catalogue et historique), dans l'ancien format de la caisse.
 */
#[Route('/admin/cash-register', name: 'cash_register_')]
#[IsGranted('ROLE_USER')]
class TransferController extends AbstractController
{
    // ── Catalogue ────────────────────────────────

    #[Route('/catalog/export', name: 'catalog_export', methods: ['GET'])]
    public function exportCatalog(CatalogJsonService $catalogJson): JsonResponse
    {
        return $this->downloadJson($catalogJson->export(), 'products.json');
    }

    #[Route('/catalog/import', name: 'catalog_import', methods: ['POST'])]
    public function importCatalog(Request $request, CatalogJsonService $catalogJson): JsonResponse
    {
        try {
            $imported = $catalogJson->import($this->readUploadedJson($request));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['imported' => $imported]);
    }

    // ── Historique d'un évènement ────────────────

    #[Route('/event/{id}/history/export', name: 'history_export', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportHistory(Event $event, SalesHistoryJsonService $historyJson, SluggerInterface $slugger): JsonResponse
    {
        $filename = sprintf('historique_ventes_%s.json', $slugger->slug($event->getName())->lower());

        return $this->downloadJson($historyJson->export($event), $filename);
    }

    #[Route('/event/{id}/history/import', name: 'history_import', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function importHistory(Event $event, Request $request, SalesHistoryJsonService $historyJson): JsonResponse
    {
        try {
            $imported = $historyJson->import($event, $this->readUploadedJson($request));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['imported' => $imported]);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Vérifie le jeton CSRF puis lit le fichier JSON envoyé dans le champ "file".
     *
     * @return array<int, mixed>
     *
     * @throws \InvalidArgumentException si le fichier est absent ou invalide
     */
    private function readUploadedJson(Request $request): array
    {
        if (!$this->isCsrfTokenValid('cash_register', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new \InvalidArgumentException('Aucun fichier reçu.');
        }

        try {
            $data = json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException("Le fichier n'est pas un JSON valide.");
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Format invalide : un tableau JSON est attendu.');
        }

        return $data;
    }

    /** @param array<int, mixed> $data */
    private function downloadJson(array $data, string $filename): JsonResponse
    {
        $response = new JsonResponse($data);
        // Emojis et accents lisibles dans le fichier exporté
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
