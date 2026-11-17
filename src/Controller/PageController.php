<?php

namespace App\Controller;

use App\Repository\Jdr\PublishedJdrRepository;
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

    // Les JDR publiés viennent de la base ; ils se gèrent dans /admin/jdrs
    #[Route('/jdrs', name: 'app_jdrs')]
    public function jdrs(PublishedJdrRepository $jdrs): Response
    {
        return $this->render('page/jdrs.html.twig', [
            'jdrs' => $jdrs->ordered(),
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
    public function impression(PublishedJdrRepository $jdrs): Response
    {
        return $this->render('print.html.twig', [
            'jdrs' => $jdrs->onlyJdr(),
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
