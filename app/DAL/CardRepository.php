<?php
/**
 * CardRepository - Acces aux donnees des cartes (cards.json).
 */
class CardRepository
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = DATA_PATH . '/cards.json';
    }

    /**
     * Retourne toutes les cartes.
     */
    public function findAll(): array
    {
        return JsonStorage::read($this->filePath);
    }

    /**
     * Trouve une carte par son ID.
     */
    public function findById(int $id): ?array
    {
        $cards = $this->findAll();
        foreach ($cards as $card) {
            if ((int)$card['id'] === $id) {
                return $card;
            }
        }
        return null;
    }

    /**
     * Recherche des cartes par numero.
     */
    public function searchByNumber(int $number): array
    {
        $cards = $this->findAll();
        return array_values(array_filter($cards, fn($c) => (int)$c['number'] === $number));
    }

    /**
     * Recherche des cartes par rarete.
     */
    public function searchByRarity(string $rarity): array
    {
        $cards = $this->findAll();
        return array_values(array_filter($cards, fn($c) => strtolower($c['rarity']) === strtolower($rarity)));
    }

    /**
     * Retourne une carte aleatoire en fonction des probabilites de rarete.
     */
    public function getRandomCard(): ?array
    {
        $cards = $this->findAll();
        if (empty($cards)) {
            return null;
        }

        // Probabilites de rarete
        $weights = [
            'Common'    => 70,
            'Rare'      => 20,
            'Epic'      => 8,
            'Legendary' => 2,
        ];

        // Grouper les cartes par rarete
        $grouped = [];
        foreach ($cards as $card) {
            $grouped[$card['rarity']][] = $card;
        }

        // Selection ponderee de la rarete
        $roll = mt_rand(1, 100);
        $cumulative = 0;
        $selectedRarity = 'Common';

        foreach ($weights as $rarity => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                $selectedRarity = $rarity;
                break;
            }
        }

        // Si aucune carte de cette rarete, fallback sur Common
        if (empty($grouped[$selectedRarity])) {
            $selectedRarity = 'Common';
            if (empty($grouped[$selectedRarity])) {
                // Fallback: carte au hasard
                return $cards[array_rand($cards)];
            }
        }

        $pool = $grouped[$selectedRarity];
        return $pool[array_rand($pool)];
    }
}
