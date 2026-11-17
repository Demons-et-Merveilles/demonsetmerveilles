<?php

namespace App\Form\Jdr;

use App\Entity\Jdr\GameMaster;
use App\Entity\Jdr\JdrFormat;
use App\Entity\Jdr\JdrType;
use App\Entity\Jdr\PublishedJdr;
use App\Repository\Jdr\GameMasterRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ajout / modification d'un JDR publié.
 * Les règles de validation sont portées par l'entité (attributs #[Assert\...]).
 * Les classes CSS "champ-large" / "champ-demi" pilotent la grille (voir public/css/formulaire.css).
 */
class PublishedJdrType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Présentation ──
            ->add('type', EnumType::class, [
                'label' => 'Type',
                'class' => JdrType::class,
                'choice_label' => static fn (JdrType $type): string => $type->label(),
                'row_attr' => ['class' => 'champ-demi'],
            ])
            ->add('format', EnumType::class, [
                'label' => 'Format de la carte',
                'class' => JdrFormat::class,
                'choice_label' => static fn (JdrFormat $format): string => $format->label(),
                'row_attr' => ['class' => 'champ-demi'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '', // la propriété est un string non nul : on évite null
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex : L\'Appel de Cthulhu'],
                'row_attr' => ['class' => 'champ-large'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Texte brut : les retours à la ligne sont conservés sur la carte.',
                'attr' => ['rows' => 7, 'maxlength' => 5000],
                'row_attr' => ['class' => 'champ-large'],
            ])

            // ── Maître de jeu et horaire ──
            ->add('gameMaster', EntityType::class, [
                'label' => 'Maître de jeu',
                'class' => GameMaster::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucun —',
                'query_builder' => static fn (GameMasterRepository $repository) => $repository
                    ->createQueryBuilder('g')
                    ->orderBy('g.name', 'ASC'),
                'row_attr' => ['class' => 'champ-demi'],
            ])
            ->add('schedule', TextType::class, [
                'label' => 'Horaire',
                'required' => false,
                'attr' => ['maxlength' => 200, 'placeholder' => 'Ex : Samedi 14h'],
                'row_attr' => ['class' => 'champ-demi'],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Texte libre',
                'required' => false,
                'help' => 'Invité, animateur… ou MJ absent de la liste. Affiché quand aucun maître de jeu n\'est choisi.',
                'attr' => ['maxlength' => 200],
                'row_attr' => ['class' => 'champ-large'],
            ])

            // ── Lien ──
            ->add('link', UrlType::class, [
                'label' => 'Lien',
                'required' => false,
                'default_protocol' => 'https', // "exemple.fr" devient "https://exemple.fr"
                'attr' => ['maxlength' => 500, 'placeholder' => 'https://…'],
                'row_attr' => ['class' => 'champ-demi'],
            ])
            ->add('linkLabel', TextType::class, [
                'label' => 'Libellé du lien',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex : Inscription ici'],
                'row_attr' => ['class' => 'champ-demi'],
            ])

            // ── Image et affichage ──
            ->add('image', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => false,
                'help' => 'JPG, PNG, WEBP ou GIF — 10 Mo maximum.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif'],
                'row_attr' => ['class' => 'champ-large champ-image'],
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
                'empty_data' => '0',
                'help' => 'Les petits numéros apparaissent en premier.',
                'attr' => ['min' => 0, 'max' => 9999],
                'row_attr' => ['class' => 'champ-demi'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PublishedJdr::class,
            'translation_domain' => false, // libellés déjà en français
        ]);
    }
}
