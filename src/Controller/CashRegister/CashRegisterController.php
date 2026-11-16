<?php

namespace App\Controller\CashRegister;

use App\Entity\CashRegister\Event;
use App\Repository\CashRegister\CategoryRepository;
use App\Repository\CashRegister\EventRepository;
use App\Repository\CashRegister\ProductRepository;
use App\Repository\CashRegister\SaleRepository;
use App\Service\CashRegister\SaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// À adapter : rôle autorisé à utiliser la caisse
#[Route('/admin/cash-register', name: 'cash_register_')]
#[IsGranted('ROLE_USER')]
class CashRegisterController extends AbstractController
{
    /** Liste des évènements + formulaire de création. */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('cash_register/events.html.twig', [
            'events' => $eventRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/event/new', name: 'event_new', methods: ['POST'])]
    public function newEvent(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('cash_register', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            $this->addFlash('error', "Le nom de l'évènement est obligatoire.");

            return $this->redirectToRoute('cash_register_index');
        }

        $event = (new Event())
            ->setName(mb_substr($name, 0, 150))
            ->setStartsAt($this->parseDate($request->request->get('startsAt')))
            ->setEndsAt($this->parseDate($request->request->get('endsAt')));

        $em->persist($event);
        $em->flush();

        return $this->redirectToRoute('cash_register_event', ['id' => $event->getId()]);
    }

    /** Écran de caisse d'un évènement. */
    #[Route('/event/{id}', name: 'event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function register(
        Event $event,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        return $this->render('cash_register/register.html.twig', [
            'event' => $event,
            'products' => $productRepository->findActiveOrdered(),
            'categories' => $categoryRepository->findWithActiveProducts(),
        ]);
    }

    /** Encaissement (appelé en AJAX par la caisse). */
    #[Route('/event/{id}/sale', name: 'sale', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sale(Event $event, Request $request, SaleService $saleService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cash_register', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_array($payload['items'] ?? null)) {
            return $this->json(['error' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $sale = $saleService->createSale($event, $payload['items']);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'reference' => $sale->getReference(),
            'total' => $sale->getTotal(),
        ], Response::HTTP_CREATED);
    }

    /** Fragment HTML du tableau d'historique, chargé dans la modale. */
    #[Route('/event/{id}/history', name: 'history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function history(Event $event, SaleRepository $saleRepository): Response
    {
        return $this->render('cash_register/_history.html.twig', [
            'sales' => $saleRepository->findByEventWithLines($event),
        ]);
    }

    /** Convertit "2026-11-07" en date, ou null si la valeur est vide / invalide. */
    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return false === $date ? null : $date;
    }
}
