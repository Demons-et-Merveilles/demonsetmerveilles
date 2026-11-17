<?php

namespace App\Controller\Admin;

use App\Entity\Jdr\PublishedJdr;
use App\Form\Jdr\PublishedJdrType;
use App\Repository\Jdr\PublishedJdrRepository;
use App\Service\JdrImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des JDR publiés : liste, ajout, modification, suppression.
 * Protégé par access_control (^/admin) dans security.yaml.
 */
#[Route('/admin/jdrs')]
final class JdrController extends AbstractController
{
    #[Route('', name: 'app_admin_jdrs', methods: ['GET'])]
    public function liste(PublishedJdrRepository $jdrs): Response
    {
        return $this->render('admin/jdr/liste.html.twig', ['jdrs' => $jdrs->ordered()]);
    }

    #[Route('/nouveau', name: 'app_admin_jdr_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(
        Request $request,
        EntityManagerInterface $em,
        PublishedJdrRepository $jdrs,
        JdrImageStorage $images,
    ): Response {
        // Le nouveau JDR est proposé à la fin de la liste par défaut
        $jdr = (new PublishedJdr())->setDisplayOrder($jdrs->nextDisplayOrder());

        return $this->traiterFormulaire($request, $jdr, $em, $images);
    }

    #[Route('/{id}/modifier', name: 'app_admin_jdr_modifier', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function modifier(
        PublishedJdr $jdr,
        Request $request,
        EntityManagerInterface $em,
        JdrImageStorage $images,
    ): Response {
        return $this->traiterFormulaire($request, $jdr, $em, $images);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_jdr_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(
        PublishedJdr $jdr,
        Request $request,
        EntityManagerInterface $em,
        JdrImageStorage $images,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('jdr_'.$jdr->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $titre = $jdr->getTitle();
        $image = $jdr->getImage();

        $em->remove($jdr);
        $em->flush();
        $images->supprimer($image); // le fichier n'est supprimé qu'une fois la ligne supprimée en base

        $this->addFlash('succes', sprintf('« %s » supprimé.', $titre));

        return $this->redirectToRoute('app_admin_jdrs');
    }

    /**
     * Affiche et traite le formulaire (ajout et modification).
     * Le jeton CSRF et la validation (contraintes de l'entité) sont gérés par le composant Form.
     */
    private function traiterFormulaire(
        Request $request,
        PublishedJdr $jdr,
        EntityManagerInterface $em,
        JdrImageStorage $images,
    ): Response {
        $form = $this->createForm(PublishedJdrType::class, $jdr);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ancienneImage = $jdr->getImage();
            $nouvelleImage = $form->get('image')->getData();
            $imageEnregistree = null;

            // L'image est vérifiée et rangée par JdrImageStorage ; un refus devient une erreur du champ
            if ($nouvelleImage instanceof UploadedFile) {
                try {
                    $imageEnregistree = $images->enregistrer($nouvelleImage);
                    $jdr->setImage($imageEnregistree);
                } catch (\InvalidArgumentException $e) {
                    $form->get('image')->addError(new FormError($e->getMessage()));
                }
            }

            // On ré-interroge le formulaire : l'erreur d'image éventuelle le rend invalide
            if ($form->isValid()) {
                $creation = $jdr->getId() === null;
                if ($creation) {
                    $em->persist($jdr);
                }
                $em->flush();

                // L'ancienne image est remplacée : on supprime son fichier
                if ($imageEnregistree !== null) {
                    $images->supprimer($ancienneImage);
                }

                $this->addFlash('succes', sprintf('« %s » %s.', $jdr->getTitle(), $creation ? 'publié' : 'modifié'));

                return $this->redirectToRoute('app_admin_jdrs');
            }
        }

        return $this->render('admin/jdr/form.html.twig', [
            'form' => $form,
            'jdr' => $jdr,
        ]);
    }
}
