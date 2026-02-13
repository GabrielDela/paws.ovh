<?php
/**
 * DeckRepository - Gestion des decks utilisateurs (decks.json).
 */
class DeckRepository
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = DATA_PATH . '/decks.json';
    }

    /**
     * Retourne tous les decks.
     */
    public function findAll(): array
    {
        return JsonStorage::read($this->filePath);
    }

    /**
     * Trouve le deck d'un utilisateur.
     */
    public function findByUsername(string $username): ?array
    {
        $decks = $this->findAll();
        foreach ($decks as $deck) {
            if ($deck['username'] === $username) {
                return $deck;
            }
        }
        return null;
    }

    /**
     * Ajoute une carte au deck d'un utilisateur.
     */
    public function addCard(string $username, int $cardId): bool
    {
        $decks = $this->findAll();
        $found = false;

        foreach ($decks as &$deck) {
            if ($deck['username'] === $username) {
                if (!in_array($cardId, $deck['cardIds'])) {
                    $deck['cardIds'][] = $cardId;
                    sort($deck['cardIds']);
                }
                $found = true;
                break;
            }
        }
        unset($deck);

        // Creer le deck si inexistant
        if (!$found) {
            $decks[] = [
                'username' => $username,
                'cardIds'  => [$cardId],
            ];
        }

        return JsonStorage::write($this->filePath, $decks);
    }

    /**
     * Retire une carte du deck d'un utilisateur.
     */
    public function removeCard(string $username, int $cardId): bool
    {
        $decks = $this->findAll();

        foreach ($decks as &$deck) {
            if ($deck['username'] === $username) {
                $deck['cardIds'] = array_values(array_filter(
                    $deck['cardIds'],
                    fn($id) => (int)$id !== $cardId
                ));
                break;
            }
        }
        unset($deck);

        return JsonStorage::write($this->filePath, $decks);
    }

    /**
     * Supprime le deck complet d'un utilisateur.
     */
    public function deleteByUsername(string $username): bool
    {
        $decks = $this->findAll();
        $filtered = array_values(array_filter($decks, fn($d) => $d['username'] !== $username));
        return JsonStorage::write($this->filePath, $filtered);
    }
}
