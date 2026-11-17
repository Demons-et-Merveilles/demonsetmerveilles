<?php

namespace App\Entity\Jdr;

/**
 * Type de carte affichée sur la page publique "JDRs et Invités".
 */
enum JdrType: string
{
    case Jdr = 'jdr';
    case Invite = 'invite';

    public function label(): string
    {
        return match ($this) {
            self::Jdr => 'JDR (avec maître de jeu)',
            self::Invite => 'Invité / animation',
        };
    }
}
