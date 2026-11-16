<?php

namespace App\Form\CashRegister;

use App\Entity\CashRegister\Category;
use App\Entity\CashRegister\Product;
use App\Repository\CashRegister\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * Formulaire de création d'un article de la caisse.
 */
class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'help' => 'Identifiant unique, par exemple P005.',
                'empty_data' => '', // évite une erreur de type PHP si le champ est vide
                'attr' => ['maxlength' => 20],
                'constraints' => [new NotBlank(message: 'La référence est obligatoire.'), new Length(max: 20)],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'attr' => ['maxlength' => 150],
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.'), new Length(max: 150)],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'html5' => true, // champ <input type="number"> : pas de souci de virgule / point
                'attr' => ['min' => 0, 'step' => '0.01'],
                'constraints' => [
                    new NotBlank(message: 'Le prix est obligatoire.'),
                    new PositiveOrZero(message: 'Le prix ne peut pas être négatif.'),
                ],
            ])
            ->add('emoji', TextType::class, [
                'label' => 'Emoji',
                'required' => false,
                'help' => 'Optionnel, par exemple 🥛. Sans emoji, 📦 est affiché.',
                'attr' => ['maxlength' => 16],
                'constraints' => [new Length(max: 16)],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Choisir une catégorie —',
                'query_builder' => static fn (CategoryRepository $repository) => $repository
                    ->createQueryBuilder('c')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('newCategory', TextType::class, [
                'label' => 'Ou nouvelle catégorie',
                'mapped' => false, // n'existe pas dans Product : traité dans le contrôleur
                'required' => false,
                'help' => 'Si rempli, ce champ est prioritaire sur la liste (la catégorie est créée si besoin).',
                'attr' => ['maxlength' => 100],
                'constraints' => [new Length(max: 100)],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Affiché à la caisse',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
