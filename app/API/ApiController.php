<?php
/**
 * ApiController - Dispatche les requetes API vers les services metier.
 */
class ApiController
{
    private AuthService $authService;
    private CardService $cardService;

    public function __construct(AuthService $authService, CardService $cardService)
    {
        $this->authService = $authService;
        $this->cardService = $cardService;
    }

    /**
     * Dispatche la requete vers le bon endpoint.
     */
    public function dispatch(string $endpoint): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $result = match ($endpoint) {
                'register'      => $this->handleRegister(),
                'login'         => $this->handleLogin(),
                'logout'        => $this->handleLogout(),
                'me'            => $this->handleMe(),
                'cards'         => $this->handleCards(),
                'deck'          => $this->handleDeck(),
                'claimDaily'    => $this->handleClaimDaily(),
                'canClaim'      => $this->handleCanClaim(),
                'adminOverview' => $this->handleAdminOverview(),
                'adminAction'   => $this->handleAdminAction(),
                default         => ['success' => false, 'error' => 'Endpoint inconnu.'],
            };

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Erreur interne du serveur.',
            ]);
        }
    }

    /**
     * POST /api?endpoint=register
     */
    private function handleRegister(): array
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();
        $username = $data['username'] ?? '';
        return $this->authService->register($username);
    }

    /**
     * POST /api?endpoint=login
     */
    private function handleLogin(): array
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        return $this->authService->login($username, $password);
    }

    /**
     * POST /api?endpoint=logout
     */
    private function handleLogout(): array
    {
        $this->requireMethod('POST');
        return $this->authService->logout();
    }

    /**
     * GET /api?endpoint=me
     */
    private function handleMe(): array
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return ['success' => false, 'error' => 'Non connecte.'];
        }
        return ['success' => true, 'user' => $user];
    }

    /**
     * GET /api?endpoint=cards&q=...&type=number|rarity
     */
    private function handleCards(): array
    {
        $query = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? 'number';
        $cards = $this->cardService->searchCards($query, $type);
        return ['success' => true, 'cards' => $cards];
    }

    /**
     * GET /api?endpoint=deck
     */
    private function handleDeck(): array
    {
        $this->requireAuth();
        $username = $_SESSION['user']['username'];
        $cards = $this->cardService->getUserDeck($username);
        return ['success' => true, 'cards' => $cards];
    }

    /**
     * POST /api?endpoint=claimDaily
     */
    private function handleClaimDaily(): array
    {
        $this->requireMethod('POST');
        $this->requireAuth();
        $username = $_SESSION['user']['username'];
        return $this->cardService->claimDailyCard($username);
    }

    /**
     * GET /api?endpoint=canClaim
     */
    private function handleCanClaim(): array
    {
        $this->requireAuth();
        $username = $_SESSION['user']['username'];
        return $this->cardService->canClaimToday($username);
    }

    /**
     * GET /api?endpoint=adminOverview
     */
    private function handleAdminOverview(): array
    {
        $this->requireAdmin();

        $users = $this->authService->getAllUsers();
        $decks = $this->cardService->getAllDecks();
        $allCards = $this->cardService->getAllCards();

        return [
            'success'  => true,
            'users'    => $users,
            'decks'    => $decks,
            'allCards' => $allCards,
        ];
    }

    /**
     * POST /api?endpoint=adminAction
     */
    private function handleAdminAction(): array
    {
        $this->requireMethod('POST');
        $this->requireAdmin();

        $data = $this->getJsonBody();
        $action = $data['action'] ?? '';

        return match ($action) {
            'regeneratePassword' => $this->authService->regeneratePassword($data['username'] ?? ''),
            'setDailyLimit'      => $this->authService->setDailyLimit($data['username'] ?? '', (int)($data['limit'] ?? 1)),
            'resetDailyClaim'    => $this->authService->resetDailyClaim($data['username'] ?? ''),
            'addCard'            => $this->cardService->addCardToUser($data['username'] ?? '', (int)($data['cardId'] ?? 0)),
            'removeCard'         => $this->cardService->removeCardFromUser($data['username'] ?? '', (int)($data['cardId'] ?? 0)),
            default              => ['success' => false, 'error' => 'Action inconnue.'],
        };
    }

    // --- Helpers ---

    private function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode non autorisee.']);
            exit;
        }
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentification requise.']);
            exit;
        }
    }

    private function requireAdmin(): void
    {
        $this->requireAuth();
        if (!$this->authService->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acces refuse.']);
            exit;
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
