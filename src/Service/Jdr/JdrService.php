<?php

namespace App\Service\Jdr;

use Psr\Log\LoggerInterface;

/**
 * Lit la liste des JDR / invités / animations dans data/jdr.json.
 *
 * Chaque entrée est un tableau associatif avec les clés possibles :
 * id, type, format, image, title, description, mastering, link, desclink, horaire.
 */
final class JdrService
{
    public function __construct(
        private readonly string $fichierJdrs,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Toutes les cartes (JDR, invités...), dans l'ordre du fichier.
     *
     * @return list<array<string, mixed>>
     */
    public function tous(): array
    {
        if (!is_file($this->fichierJdrs)) {
            $this->logger->error('Fichier des JDR introuvable : {fichier}', ['fichier' => $this->fichierJdrs]);

            return [];
        }

        try {
            $donnees = json_decode(file_get_contents($this->fichierJdrs), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('JSON des JDR invalide : {message}', ['message' => $e->getMessage()]);

            return [];
        }

        return is_array($donnees) ? array_values($donnees) : [];
    }

    /**
     * Uniquement les cartes de type "jdr" (utilisé par la page d'impression).
     *
     * @return list<array<string, mixed>>
     */
    public function seulementJdr(): array
    {
        return array_values(array_filter(
            $this->tous(),
            static fn (array $carte): bool => ($carte['type'] ?? null) === 'jdr',
        ));
    }
}
