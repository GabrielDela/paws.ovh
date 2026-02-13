<?php
/**
 * CardService - Logique metier pour les cartes et les decks.
 */
class CardService
{
    private CardRepository $cardRepo;
    private DeckRepository $deckRepo;
    private UserRepository $userRepo;

    public function __construct(CardRepository $cardRepo, DeckRepository $deckRepo, UserRepository $userRepo)
    {
        $this->cardRepo = $cardRepo;
        $this->deckRepo = $deckRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Recherche des cartes avec filtres.
     */
    public function searchCards(string $query = '', string $type = 'number'): array
    {
        if (empty($query)) {
            return $this->cardRepo->findAll();
        }

        if ($type === 'rarity') {
            return $this->cardRepo->searchByRarity($query);
        }

        // Recherche par numero (defaut)
        if (is_numeric($query)) {
            return $this->cardRepo->searchByNumber((int)$query);
        }

        // Recherche par nom si pas numerique
        $cards = $this->cardRepo->findAll();
        $query = strtolower($query);
        return array_values(array_filter($cards, function ($c) use ($query) {
            return str_contains(strtolower($c['name']), $query);
        }));
    }

    /**
     * Retourne toutes les cartes.
     */
    public function getAllCards(): array
    {
        return $this->cardRepo->findAll();
    }

    /**
     * Retourne le deck d'un utilisateur avec les details des cartes.
     */
    public function getUserDeck(string $username): array
    {
        $deck = $this->deckRepo->findByUsername($username);
        if ($deck === null) {
            return [];
        }

        $cards = [];
        foreach ($deck['cardIds'] as $cardId) {
            $card = $this->cardRepo->findById((int)$cardId);
            if ($card) {
                $cards[] = $card;
            }
        }

        // Trier par numero
        usort($cards, fn($a, $b) => $a['number'] <=> $b['number']);
        return $cards;
    }

    /**
     * Reclame la carte du jour pour un utilisateur.
     */
    public function claimDailyCard(string $username): array
    {
        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['success' => false, 'error' => 'Utilisateur introuvable.'];
        }

        $today = date('Y-m-d');
        $lastClaim = $user['lastClaimDate'] ?? null;
        $claimsToday = (int)($user['claimsToday'] ?? 0);
        $dailyLimit = (int)($user['dailyLimit'] ?? 1);

        // Reset si nouveau jour
        if ($lastClaim !== $today) {
            $claimsToday = 0;
        }

        // Verifier la limite
        if ($claimsToday >= $dailyLimit) {
            return [
                'success' => false,
                'error'   => 'Vous avez deja recupere votre(vos) carte(s) du jour. Revenez demain !',
            ];
        }

        // Obtenir une carte aleatoire
        $card = $this->cardRepo->getRandomCard();
        if ($card === null) {
            return ['success' => false, 'error' => 'Aucune carte disponible.'];
        }

        // Ajouter au deck
        $this->deckRepo->addCard($username, $card['id']);

        // Mettre a jour le claim
        $this->userRepo->update($username, [
            'lastClaimDate' => $today,
            'claimsToday'   => $claimsToday + 1,
        ]);

        return [
            'success'    => true,
            'card'       => $card,
            'remaining'  => $dailyLimit - $claimsToday - 1,
        ];
    }

    /**
     * Verifie si l'utilisateur peut encore reclamer une carte aujourd'hui.
     */
    public function canClaimToday(string $username): array
    {
        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['canClaim' => false, 'remaining' => 0];
        }

        $today = date('Y-m-d');
        $lastClaim = $user['lastClaimDate'] ?? null;
        $claimsToday = (int)($user['claimsToday'] ?? 0);
        $dailyLimit = (int)($user['dailyLimit'] ?? 1);

        if ($lastClaim !== $today) {
            $claimsToday = 0;
        }

        $remaining = $dailyLimit - $claimsToday;
        return [
            'canClaim'  => $remaining > 0,
            'remaining' => max(0, $remaining),
            'limit'     => $dailyLimit,
        ];
    }

    /**
     * Ajoute une carte au deck d'un utilisateur (admin).
     */
    public function addCardToUser(string $username, int $cardId): array
    {
        $card = $this->cardRepo->findById($cardId);
        if ($card === null) {
            return ['success' => false, 'error' => 'Carte introuvable.'];
        }

        $user = $this->userRepo->findByUsername($username);
        if ($user === null) {
            return ['success' => false, 'error' => 'Utilisateur introuvable.'];
        }

        $this->deckRepo->addCard($username, $cardId);
        return ['success' => true];
    }

    /**
     * Retire une carte du deck d'un utilisateur (admin).
     */
    public function removeCardFromUser(string $username, int $cardId): array
    {
        $this->deckRepo->removeCard($username, $cardId);
        return ['success' => true];
    }

    /**
     * Retourne les decks de tous les utilisateurs avec details (admin).
     */
    public function getAllDecks(): array
    {
        $decks = $this->deckRepo->findAll();
        $result = [];

        foreach ($decks as $deck) {
            $cards = [];
            foreach ($deck['cardIds'] as $cardId) {
                $card = $this->cardRepo->findById((int)$cardId);
                if ($card) {
                    $cards[] = $card;
                }
            }
            usort($cards, fn($a, $b) => $a['number'] <=> $b['number']);

            $result[$deck['username']] = $cards;
        }

        return $result;
    }
}
