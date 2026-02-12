<?php

declare(strict_types=1);

namespace App\API;

use App\BLL\AuthService;
use App\BLL\CardService;

class ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CardService $cards
    ) {
    }

    public function dispatch(string $endpoint): void
    {
        try {
            switch ($endpoint) {
                case 'register':
                    $this->json($this->auth->register($_POST['username'] ?? '', $_SERVER['REMOTE_ADDR'] ?? 'local'));
                    return;
                case 'login':
                    $this->json($this->auth->login($_POST['username'] ?? '', $_POST['password'] ?? ''));
                    return;
                case 'logout':
                    $this->auth->logout();
                    $this->json(['ok' => true]);
                    return;
                case 'cards':
                    $this->requireAuth();
                    $this->json($this->cards->search($_GET['number'] ?? null, $_GET['rarity'] ?? null));
                    return;
                case 'deck':
                    $user = $this->requireAuth();
                    $this->json($this->cards->getDeck($user['username']));
                    return;
                case 'claimDaily':
                    $user = $this->requireAuth();
                    $this->json($this->cards->claimDailyCard($user['username']));
                    return;
                case 'adminOverview':
                    $this->requireAdmin();
                    $this->json($this->cards->adminOverview());
                    return;
                case 'adminAction':
                    $this->requireAdmin();
                    $this->json($this->cards->adminAction(
                        $_POST['action'] ?? '',
                        $_POST['username'] ?? '',
                        $_POST
                    ));
                    return;
                default:
                    http_response_code(404);
                    $this->json(['error' => 'Endpoint non trouvé']);
                    return;
            }
        } catch (\Throwable $exception) {
            http_response_code(400);
            $this->json(['error' => $exception->getMessage()]);
        }
    }

    private function requireAuth(): array
    {
        if (!isset($_SESSION['user'])) {
            throw new \RuntimeException('Authentification requise.');
        }
        return $_SESSION['user'];
    }

    private function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Droits admin requis.');
        }
        return $user;
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
