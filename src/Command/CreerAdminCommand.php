<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:creer-admin', description: 'Crée un utilisateur administrateur')]
final class CreerAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifiant', InputArgument::REQUIRED, 'Identifiant de connexion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifiant = $input->getArgument('identifiant');

        // On refuse de créer un doublon
        if ($this->entityManager->getRepository(User::class)->findOneBy(['identifiant' => $identifiant])) {
            $io->error(sprintf('L\'identifiant "%s" existe déjà.', $identifiant));

            return Command::FAILURE;
        }

        // Saisie masquée du mot de passe
        $motDePasse = $io->askHidden('Mot de passe');
        if (empty($motDePasse)) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setIdentifiant($identifiant);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $motDePasse));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Administrateur "%s" créé.', $identifiant));

        return Command::SUCCESS;
    }
}
