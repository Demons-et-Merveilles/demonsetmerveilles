<?php

namespace App\Entity\Jdr;

/**
 * Taille de la carte dans la grille : une colonne ou pleine largeur.
 */
enum JdrFormat: string
{
    case Simple = 'simple';
    case Double = 'double';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple (une colonne)',
            self::Double => 'Double (pleine largeur)',
        };
    }
}
