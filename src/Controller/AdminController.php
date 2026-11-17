<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// L'accès est protégé par access_control (^/admin) dans security.yaml
#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin')]
    public function index(): Response
    {

        $modules = [
            [
                'label' => 'Jdrs publiés',
                'icon' => '🎲',
                'description' => 'Gérer les JDRs publiés',
                'route' => 'app_admin_jdrs',
            ],
            [
                'label' => 'Caisse',
                'icon' => '🧾',
                'description' => 'Encaisser les ventes et consulter l\'historique par évènement.',
                'route' => 'cash_register_index',
            ],
            [
                'label' => 'Tables de JDR',
                'icon' => '🎲',
                'description' => 'Répartir les MJ et les joueurs sur les tables en glisser-déposer.',
                'route' => 'jdr_index',
            ],
            [
                'label' => 'Impression',
                'icon' => '📄',
                'description' => 'Imprimer les designs des tables de JDRs',
                'route' => 'app_print',
            ],
        ];

        return $this->render('admin/menu.html.twig', ['modules' => $modules]);
    }
}
