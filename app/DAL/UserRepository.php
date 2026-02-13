<?php
/**
 * UserRepository - Acces aux donnees utilisateurs (users.json).
 */
class UserRepository
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = DATA_PATH . '/users.json';
    }

    /**
     * Retourne tous les utilisateurs.
     */
    public function findAll(): array
    {
        return JsonStorage::read($this->filePath);
    }

    /**
     * Trouve un utilisateur par son username.
     */
    public function findByUsername(string $username): ?array
    {
        $users = $this->findAll();
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Cree un nouvel utilisateur.
     */
    public function create(array $userData): bool
    {
        $users = $this->findAll();
        $users[] = $userData;
        return JsonStorage::write($this->filePath, $users);
    }

    /**
     * Met a jour un utilisateur existant par son username.
     */
    public function update(string $username, array $newData): bool
    {
        $users = $this->findAll();
        $updated = false;

        foreach ($users as &$user) {
            if ($user['username'] === $username) {
                $user = array_merge($user, $newData);
                $updated = true;
                break;
            }
        }
        unset($user);

        if ($updated) {
            return JsonStorage::write($this->filePath, $users);
        }
        return false;
    }

    /**
     * Supprime un utilisateur par son username.
     */
    public function delete(string $username): bool
    {
        $users = $this->findAll();
        $filtered = array_values(array_filter($users, fn($u) => $u['username'] !== $username));

        if (count($filtered) === count($users)) {
            return false;
        }

        return JsonStorage::write($this->filePath, $filtered);
    }
}
