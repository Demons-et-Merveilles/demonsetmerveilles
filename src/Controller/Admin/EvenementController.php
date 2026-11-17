<?php

namespace App\Controller\Admin;

use App\Entity\Evenement;
use App\Entity\SessionVente;
use App\Repository\EvenementRepository;
use App\Repository\VenteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des évènements et de leurs sessions de vente (création, archivage).
 * Protégé par access_control (^/admin) dans security.yaml.
 */
final class EvenementController extends AbstractController
{
    #[Route('/admin/evenements', name: 'app_evenements', methods: ['GET'])]
    public function liste(EvenementRepository $evenements, VenteRepository $ventes): Response
    {
        $tous = $evenements->tousAvecSessions();

        return $this->render('admin/evenement/liste.html.twig', [
            'actifs' => array_filter($tous, static fn (Evenement $e): bool => !$e->estArchive()),
            'archives' => array_filter($tous, static fn (Evenement $e): bool => $e->estArchive()),
            'stats' => $ventes->statsParSession(),
        ]);
    }

    #[Route('/admin/evenements/nouveau', name: 'app_evenement_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'evenement_nouveau');

        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '' || mb_strlen($nom) > 150) {
            $this->addFlash('erreur', 'Le nom de l\'évènement est obligatoire (150 caractères max).');

            return $this->redirectToRoute('app_evenements');
        }

        $evenement = (new Evenement($nom))
            ->setDateDebut($this->lireDate($request->request->get('date_debut')))
            ->setDateFin($this->lireDate($request->request->get('date_fin')));

        $em->persist($evenement);
        $em->flush();

        $this->addFlash('succes', sprintf('Évènement « %s » créé.', $nom));

        return $this->redirectToRoute('app_evenements');
    }

    #[Route('/admin/evenements/{id}/archiver', name: 'app_evenement_archiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archiver(Evenement $evenement, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'evenement_'.$evenement->getId());

        $evenement->setArchive(true);
        $em->flush();

        $this->addFlash('succes', sprintf('Évènement « %s » archivé : ses sessions ne sont plus utilisables en caisse.', $evenement->getNom()));

        return $this->redirectToRoute('app_evenements');
    }

    #[Route('/admin/evenements/{id}/desarchiver', name: 'app_evenement_desarchiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function desarchiver(Evenement $evenement, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'evenement_'.$evenement->getId());

        // Les sessions retrouvent l'état qu'elles avaient avant l'archivage de l'évènement
        $evenement->setArchive(false);
        $em->flush();

        $this->addFlash('succes', sprintf('Évènement « %s » restauré.', $evenement->getNom()));

        return $this->redirectToRoute('app_evenements');
    }

    #[Route('/admin/evenements/{id}/sessions', name: 'app_session_nouvelle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function nouvelleSession(Evenement $evenement, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'session_nouvelle_'.$evenement->getId());

        if ($evenement->estArchive()) {
            $this->addFlash('erreur', 'Impossible d\'ajouter une session à un évènement archivé.');

            return $this->redirectToRoute('app_evenements');
        }

        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '') {
            $nom = 'Session du '.(new \DateTimeImmutable())->format('d/m/Y H:i');
        }
        if (mb_strlen($nom) > 150) {
            $this->addFlash('erreur', 'Le nom de la session est trop long (150 caractères max).');

            return $this->redirectToRoute('app_evenements');
        }

        $session = new SessionVente($evenement, $nom);
        $em->persist($session);
        $em->flush();

        $this->addFlash('succes', sprintf('Session « %s » ouverte.', $nom));

        return $this->redirectToRoute('app_evenements');
    }

    #[Route('/admin/sessions/{id}', name: 'app_session_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detailSession(SessionVente $session, VenteRepository $ventes): Response
    {
        $liste = $ventes->ventesDeSession($session);

        return $this->render('admin/evenement/session.html.twig', [
            'session' => $session,
            'ventes' => $liste,
            'total' => array_sum(array_map(static fn ($v): float => $v->getTotal(), $liste)),
        ]);
    }

    #[Route('/admin/sessions/{id}/archiver', name: 'app_session_archiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archiverSession(SessionVente $session, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'session_'.$session->getId());

        $session->archiver();
        $em->flush();

        $this->addFlash('succes', sprintf('Session « %s » archivée.', $session->getNom()));

        return $this->redirectToRoute('app_evenements');
    }

    #[Route('/admin/sessions/{id}/desarchiver', name: 'app_session_desarchiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function desarchiverSession(SessionVente $session, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->verifierCsrf($request, 'session_'.$session->getId());

        if ($session->getEvenement()->estArchive()) {
            $this->addFlash('erreur', 'Restaurez d\'abord l\'évènement archivé.');

            return $this->redirectToRoute('app_evenements');
        }

        $session->desarchiver();
        $em->flush();

        $this->addFlash('succes', sprintf('Session « %s » rouverte.', $session->getNom()));

        return $this->redirectToRoute('app_evenements');
    }

    // Refuse la requête si le jeton CSRF du formulaire est absent ou invalide
    private function verifierCsrf(Request $request, string $idJeton): void
    {
        if (!$this->isCsrfTokenValid($idJeton, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    // Convertit une date "AAAA-MM-JJ" venant d'un <input type="date">, ou null si vide / invalide
    private function lireDate(mixed $valeur): ?\DateTimeImmutable
    {
        if (!is_string($valeur) || $valeur === '') {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur) ?: null;
    }
}
