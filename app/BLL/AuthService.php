<?php

declare(strict_types=1);

namespace App\BLL;

use App\DAL\DeckRepository;
use App\DAL\UserRepository;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DeckRepository $decks
    ) {
    }

    public function register(string $username, string $ipAddress): array
    {
        if (!$this->canRegister($ipAddress)) {
            throw new \RuntimeException('Trop de tentatives. Réessayez dans quelques minutes.');
        }

        $username = trim($username);
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            throw new \InvalidArgumentException('Username invalide (3-20, lettres/chiffres/_).');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new \RuntimeException('Ce username existe déjà.');
        }

        $plainPassword = bin2hex(random_bytes(5)) . 'A!';
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $allUsers = $this->users->all();
        $allUsers[] = [
            'username' => $username,
            'passwordHash' => $passwordHash,
            'role' => 'user',
            'dailyLimit' => 1,
            'lastClaimDate' => null,
            'createdAt' => date(DATE_ATOM),
        ];
        $this->users->saveAll($allUsers);

        $allDecks = $this->decks->all();
        $allDecks[] = [
            'username' => $username,
            'cardIds' => [],
        ];
        $this->decks->saveAll($allDecks);

        $_SESSION['register_rate'][$ipAddress][] = time();

        return ['username' => $username, 'password' => $plainPassword];
    }

    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(trim($username));
        if ($user === null || !password_verify($password, $user['passwordHash'])) {
            throw new \RuntimeException('Identifiants invalides.');
        }

        $_SESSION['user'] = [
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        session_regenerate_id(true);

        return $_SESSION['user'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    private function canRegister(string $ipAddress): bool
    {
        $_SESSION['register_rate'] ??= [];
        $_SESSION['register_rate'][$ipAddress] ??= [];

        $now = time();
        $_SESSION['register_rate'][$ipAddress] = array_values(array_filter(
            $_SESSION['register_rate'][$ipAddress],
            fn (int $timestamp) => ($now - $timestamp) < 300
        ));

        return count($_SESSION['register_rate'][$ipAddress]) < 3;
    }
}
