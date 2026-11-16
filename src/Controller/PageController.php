<?php

namespace App\Controller;

use App\Service\JdrService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function accueil(): Response
    {
        return $this->render('page/accueil.html.twig');
    }

    #[Route('/jdrs', name: 'app_jdrs')]
    public function jdrs(JdrService $jdrService): Response
    {
        return $this->render('page/jdrs.html.twig', [
            'cartes' => $jdrService->tous(),
        ]);
    }

    #[Route('/portes-ouvertes', name: 'app_portes_ouvertes')]
    public function portesOuvertes(
        #[Autowire('%app.date_portes_ouvertes%')] string $dateCible,
    ): Response {
        return $this->render('page/portes-ouvertes.html.twig', [
            'date_cible' => $dateCible,
        ]);
    }

    /**
     * Page d'impression d'une fiche JDR (sans menu, sans lien).
     */
    #[Route('/print', name: 'app_print')]
    public function impression(JdrService $jdrService): Response
    {
        return $this->render('print.html.twig', [
            'jdrs' => $jdrService->seulementJdr(),
        ]);
    }

    /**
     * Pages purement statiques : un seul point d'entrée.
     * Pour ajouter une page : créer templates/page/<nom>.html.twig
     * et ajouter son nom dans la regex ci-dessous.
     */
    #[Route(
        '/{page}',
        name: 'app_page',
        requirements: ['page' => 'agenda|mtg|bloodbowl|concours-peinture|histoire|gn|contact'],
    )]
    public function page(string $page): Response
    {
        return $this->render(sprintf('page/%s.html.twig', $page));
    }
}
