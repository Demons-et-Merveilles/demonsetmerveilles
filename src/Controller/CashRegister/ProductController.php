<?php

namespace App\Controller\CashRegister;

use App\Entity\CashRegister\Category;
use App\Entity\CashRegister\Product;
use App\Form\CashRegister\ProductType;
use App\Repository\CashRegister\CategoryRepository;
use App\Repository\CashRegister\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// À adapter : rôle autorisé à créer des articles
#[Route('/admin/cash-register/product', name: 'cash_register_product_')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    /**
     * Création d'un article.
     * Paramètre optionnel ?event=ID : permet de revenir à la caisse de cet évènement après la création.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        EventRepository $eventRepository,
    ): Response {
        $event = $eventRepository->find($request->query->getInt('event'));

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $this->resolveCategory($form, $product, $categoryRepository);

            if (null === $category) {
                $form->get('category')->addError(new FormError('Choisissez une catégorie ou saisissez-en une nouvelle.'));
            } else {
                $product->setCategory($category);
                $em->persist($category); // sans effet si la catégorie existe déjà
                $em->persist($product);
                $em->flush();

                $this->addFlash('success', sprintf('Article « %s » créé.', $product->getName()));

                return null !== $event
                    ? $this->redirectToRoute('cash_register_event', ['id' => $event->getId()])
                    : $this->redirectToRoute('cash_register_product_new');
            }
        }

        return $this->render('cash_register/product_new.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    /** Catégorie saisie à la main (prioritaire) sinon catégorie choisie dans la liste. */
    private function resolveCategory(FormInterface $form, Product $product, CategoryRepository $categoryRepository): ?Category
    {
        $newName = trim((string) $form->get('newCategory')->getData());

        if ('' === $newName) {
            return $product->getCategory();
        }

        return $categoryRepository->findOneBy(['name' => $newName])
            ?? (new Category())->setName($newName);
    }
}
