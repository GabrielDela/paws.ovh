<?php
/**
 * AuthService - Logique metier d'authentification et gestion utilisateurs.
 */
class AuthService
{
    private UserRepository $userRepo;
    private DeckRepository $deckRepo;

    public function __construct(UserRepository $userRepo, DeckRepository $deckRepo)
    {
        $this->userRepo = $userRepo;
        $this->deckRepo = $deckRepo;
    }

    /**
     * Inscrit un nouvel utilisateur.
     * Retourne le mot de passe genere (affiche une seule fois) ou une erreur.
     */
    public function register(string $username): array
    {
        // Validation du username
        $username = trim($username);
        if (strlen($username) < 3 || strlen($username) > 20) {
            return ['success' => false, 'error' => 'Le nom d\'utilisateur doit faire entre 3 et 20 caracteres.'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'error' => 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres et underscores.'];
        }

        // Verifier rate limit
        if ($this->isRateLimited()) {
            return ['success' => false, 'error' => 'Trop de tentatives. Veuillez reessayer dans quelques minutes.'];
        }

        // Verifier si l'utilisateur existe deja
        if ($this->userRepo->findByUsername($username) !== null) {
            return ['success' => false, 'error' => 'Ce nom d\'utilisateur est deja pris.'];
        }

        // Generer un mot de passe securise
        $password = $this->generateSecurePassword();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Creer l'utilisateur
        $user = [
            'username'      => $username,
            'passwordHash'  => $hash,
            'role'          => 'user',
            'dailyLimit'    => 1,
            'lastClaimDate' => null,
            'claimsToday'   => 0,
            'createdAt'     => date('c'),
        ];

        $this->userRepo->create($user);

        // Enregistrer le rate limit
        $this->recordRegisterAttempt();

        return [
            'success'  => true,
            'password' => $password,
            'message'  => 'Compte cree avec succes. Notez bien votre mot de passe, il ne sera plus affiche.',
        ];
    }

    /**
     * Connecte un utilisateur.
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        $user = $this->userRepo->findByUsername($username);

        if ($user === null || !password_verify($password, $user['passwordHash'])) {
            return ['success' => false, 'error' => 'Identifiants incorrects.'];
        }

        // Regenerer la session pour eviter le fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'username' => $user['username'],
            'role'     => $user['role'],
        ];

        return [
            'success' => true,
            'user'    => [
                'username' => $user['username'],
                'role'     => $user['role'],
            ],
        ];
    }

    /**
     * Deconnecte l'utilisateur.
     */
    public function logout(): array
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        return ['success' => true];
    }

    /**
     * Retourne l'utilisateur connecte ou null.
     */
    public function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Verifie si l'utilisateur courant est admin.
     */
    public function isAdmin(): bool
    {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    }

    /**
     * Regenere le mot de passe d'un utilisateur (admin only).
     */
    public function regeneratePassword(string $username): array
    {
        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['success' => false, 'error' => 'Utilisateur introuvable.'];
        }

        $password = $this->generateSecurePassword();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->userRepo->update($username, ['passwordHash' => $hash]);

        return [
            'success'  => true,
            'password' => $password,
        ];
    }

    /**
     * Retourne la liste de tous les utilisateurs (admin overview).
     */
    public function getAllUsers(): array
    {
        $users = $this->userRepo->findAll();
        // Ne pas exposer les hash de mot de passe
        return array_map(function ($u) {
            return [
                'username'      => $u['username'],
                'role'          => $u['role'],
                'dailyLimit'    => $u['dailyLimit'] ?? 1,
                'lastClaimDate' => $u['lastClaimDate'] ?? null,
                'claimsToday'   => $u['claimsToday'] ?? 0,
                'createdAt'     => $u['createdAt'] ?? null,
            ];
        }, $users);
    }

    /**
     * Modifie la limite quotidienne de cartes d'un utilisateur (admin).
     */
    public function setDailyLimit(string $username, int $limit): array
    {
        if ($limit < 1 || $limit > 50) {
            return ['success' => false, 'error' => 'La limite doit etre entre 1 et 50.'];
        }

        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['success' => false, 'error' => 'Utilisateur introuvable.'];
        }

        $this->userRepo->update($username, ['dailyLimit' => $limit]);
        return ['success' => true];
    }

    /**
     * Reset le claim journalier d'un utilisateur (admin).
     */
    public function resetDailyClaim(string $username): array
    {
        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['success' => false, 'error' => 'Utilisateur introuvable.'];
        }

        $this->userRepo->update($username, ['lastClaimDate' => null, 'claimsToday' => 0]);
        return ['success' => true];
    }

    /**
     * Genere un mot de passe securise de 16 caracteres.
     */
    private function generateSecurePassword(): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';
        $special = '!@#$%&*';

        // Au moins un caractere de chaque type
        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $upper . $lower . $digits . $special;
        for ($i = 4; $i < 16; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Melanger
        return str_shuffle($password);
    }

    /**
     * Verifie le rate limit pour l'inscription (3 par IP par 5 min).
     */
    private function isRateLimited(): bool
    {
        $key = 'register_attempts';
        if (!isset($_SESSION[$key])) {
            return false;
        }

        $attempts = $_SESSION[$key];
        $now = time();

        // Nettoyer les tentatives expirees (> 5 min)
        $attempts = array_filter($attempts, fn($t) => ($now - $t) < 300);
        $_SESSION[$key] = $attempts;

        return count($attempts) >= 3;
    }

    /**
     * Enregistre une tentative d'inscription pour le rate limit.
     */
    private function recordRegisterAttempt(): void
    {
        $key = 'register_attempts';
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key][] = time();
    }
}
