<?php

namespace App\Command;

use App\Service\Jdr\JdrBoardService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:jdr:init', description: 'Crée les MJ et les tables par défaut du plateau JDR (si le plateau est vide)')]
class InitJdrBoardCommand extends Command
{
    public function __construct(private readonly JdrBoardService $board)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->board->initializeDefaults()) {
            $io->warning("Le plateau contient déjà des données : rien n'a été créé.");

            return Command::SUCCESS;
        }

        $io->success('6 MJ (MJ1 à MJ6) et 4 tables (Table 1 à Table 4) créés.');

        return Command::SUCCESS;
    }
}
