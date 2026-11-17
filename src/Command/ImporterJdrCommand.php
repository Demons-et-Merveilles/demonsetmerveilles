<?php

namespace App\Command;

use App\Entity\Jdr\GameMaster;
use App\Entity\Jdr\JdrFormat;
use App\Entity\Jdr\JdrType;
use App\Entity\Jdr\PublishedJdr;
use App\Repository\Jdr\GameMasterRepository;
use App\Repository\Jdr\PublishedJdrRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Migration unique : reprend l'ancien fichier data/jdr.json dans la table jdr_published.
 * Peut être supprimée une fois la migration faite.
 */
#[AsCommand(name: 'app:jdr:importer', description: 'Importe les anciens JDR depuis data/jdr.json (migration unique)')]
final class ImporterJdrCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameMasterRepository $gameMasters,
        private readonly PublishedJdrRepository $jdrs,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $racine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::OPTIONAL, 'Fichier JSON à importer', 'data/jdr.json')
            ->addOption('creer-mj', null, InputOption::VALUE_NONE, 'Crée un GameMaster quand le nom du MJ n\'existe pas encore')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Importe même si la table contient déjà des JDR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Chemin relatif = relatif à la racine du projet
        $fichier = (string) $input->getArgument('fichier');
        $chemin = preg_match('#^([A-Za-z]:)?[\\\\/]#', $fichier) ? $fichier : $this->racine.'/'.$fichier;

        if (!is_file($chemin)) {
            $io->error('Fichier introuvable : '.$chemin);

            return Command::FAILURE;
        }
        if ($this->jdrs->count([]) > 0 && !$input->getOption('force')) {
            $io->error('La table contient déjà des JDR. Relancez avec --force pour importer quand même (risque de doublons).');

            return Command::FAILURE;
        }

        try {
            $cartes = json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('JSON invalide : '.$e->getMessage());

            return Command::FAILURE;
        }
        if (!is_array($cartes)) {
            $io->error('Le fichier doit contenir un tableau de cartes.');

            return Command::FAILURE;
        }

        $creerMj = (bool) $input->getOption('creer-mj');
        $mjCrees = []; // nom => GameMaster créés pendant cet import
        $importes = 0;

        foreach (array_values($cartes) as $index => $carte) {
            $titre = is_array($carte) ? trim((string) ($carte['title'] ?? '')) : '';
            if ($titre === '') {
                $io->warning(sprintf('Carte n°%d ignorée : titre manquant.', $index + 1));
                continue;
            }

            $type = JdrType::tryFrom((string) ($carte['type'] ?? ''));
            if ($type === null) {
                $io->warning(sprintf('« %s » : type "%s" inconnu, importé comme invité.', $titre, $carte['type'] ?? ''));
                $type = JdrType::Invite;
            }

            $jdr = (new PublishedJdr())
                ->setType($type)
                ->setFormat(($carte['format'] ?? '') === 'double' ? JdrFormat::Double : JdrFormat::Simple)
                ->setTitle(mb_substr($titre, 0, 150))
                ->setDescription($this->nettoyerTexte($carte['description'] ?? null))
                ->setImage($this->normaliserImage($carte['image'] ?? null))
                ->setLink($this->nullSiVide($carte['link'] ?? null, 500))
                ->setLinkLabel($this->nullSiVide($carte['desclink'] ?? null, 150))
                ->setSchedule($this->nullSiVide($carte['horaire'] ?? null, 200))
                ->setDisplayOrder($index + 1);

            // "mastering" : nom du MJ pour un JDR, texte libre pour un invité
            $nom = trim((string) ($carte['mastering'] ?? ''));
            if ($nom !== '') {
                $mj = null;
                if ($type === JdrType::Jdr) {
                    $mj = $mjCrees[$nom] ?? $this->gameMasters->findOneBy(['name' => $nom]);
                    if ($mj === null && $creerMj && mb_strlen($nom) <= 50) {
                        $mj = (new GameMaster())->setName($nom);
                        $this->em->persist($mj);
                        $mjCrees[$nom] = $mj;
                    }
                }

                if ($mj !== null) {
                    $jdr->setGameMaster($mj);
                } else {
                    // Pas de MJ correspondant : le nom est gardé en texte libre
                    $jdr->setSubtitle(mb_substr($nom, 0, 200));
                    if ($type === JdrType::Jdr) {
                        $io->note(sprintf('« %s » : MJ "%s" absent de la table des MJ, gardé en texte libre.', $titre, $nom));
                    }
                }
            }

            $this->em->persist($jdr);
            ++$importes;
        }

        $this->em->flush();

        $io->success(sprintf('%d carte(s) importée(s), %d MJ créé(s).', $importes, count($mjCrees)));

        return Command::SUCCESS;
    }

    // L'ancien site acceptait du HTML dans les descriptions : on le convertit en texte brut
    private function nettoyerTexte(mixed $texte): ?string
    {
        $texte = trim((string) $texte);
        if ($texte === '') {
            return null;
        }
        $texte = preg_replace('#<br\s*/?>#i', "\n", $texte);
        $texte = html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim($texte), 0, 5000);
    }

    // "./images/x.png" devient "images/x.png" (chemin relatif à public/)
    private function normaliserImage(mixed $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }

        return mb_substr(ltrim(preg_replace('#^\./#', '', $image), '/'), 0, 255);
    }

    private function nullSiVide(mixed $valeur, int $max): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur !== '' ? mb_substr($valeur, 0, $max) : null;
    }
}
