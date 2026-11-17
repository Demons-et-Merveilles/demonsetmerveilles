<?php

namespace App\Service\Jdr;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre et supprime les images des JDR dans public/uploads/jdr/.
 * Le type réel de l'image est vérifié avec getimagesize() (pas seulement l'extension).
 */
final class JdrImageStorage
{
    // Préfixe du chemin stocké en base pour les images envoyées par l'interface
    public const PREFIXE = 'uploads/jdr/';

    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    private const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/jdr')]
        private readonly string $dossier,
    ) {
    }

    /**
     * Enregistre l'image et retourne son chemin relatif à public/.
     *
     * @throws \InvalidArgumentException si le fichier est refusé
     */
    public function enregistrer(UploadedFile $fichier): string
    {
        if (!$fichier->isValid()) {
            throw new \InvalidArgumentException('Envoi de l\'image impossible : '.$fichier->getErrorMessage());
        }
        if ($fichier->getSize() > self::TAILLE_MAX) {
            throw new \InvalidArgumentException('L\'image dépasse 10 Mo.');
        }

        $infos = @getimagesize($fichier->getPathname());
        $extension = $infos !== false ? (self::EXTENSIONS[$infos[2]] ?? null) : null;
        if ($extension === null) {
            throw new \InvalidArgumentException('Format d\'image non accepté (JPG, PNG, WEBP ou GIF).');
        }

        $nom = bin2hex(random_bytes(8)).'.'.$extension;
        $fichier->move($this->dossier, $nom);

        return self::PREFIXE.$nom;
    }

    /**
     * Supprime une image envoyée par l'interface.
     * Les images historiques (ex : images/jdr/x.png) ne sont jamais touchées.
     */
    public function supprimer(?string $chemin): void
    {
        if ($chemin === null || !str_starts_with($chemin, self::PREFIXE)) {
            return;
        }

        $cible = $this->dossier.'/'.basename($chemin);
        if (is_file($cible)) {
            @unlink($cible);
        }
    }
}
