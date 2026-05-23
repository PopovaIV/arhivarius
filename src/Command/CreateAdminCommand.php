<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Создать первого администратора')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Создание администратора');

        $username = $io->ask('Логин', 'admin', function ($v) {
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', (string) $v)) {
                throw new \RuntimeException('Только латиница, цифры, . _ -');
            }
            return $v;
        });

        if ($this->users->findOneBy(['username' => $username]) !== null) {
            $io->error('Пользователь с таким логином уже существует');
            return Command::FAILURE;
        }

        $displayName = $io->ask('Отображаемое имя', 'Администратор');
        $email = $io->ask('Email (необязательно)');

        $passwordQuestion = new Question('Пароль (минимум 8 символов): ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setValidator(function ($v) {
            if (mb_strlen((string) $v) < 8) {
                throw new \RuntimeException('Минимум 8 символов');
            }
            return $v;
        });
        $password = $helper->ask($input, $output, $passwordQuestion);

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName($displayName);
        $user->setEmail($email);
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->users->save($user);

        $io->success("Администратор {$username} создан. Можно войти на /login");
        return Command::SUCCESS;
    }
}
