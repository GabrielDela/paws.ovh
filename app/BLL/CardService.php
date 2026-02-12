<?php

declare(strict_types=1);

namespace App\BLL;

use App\DAL\CardRepository;
use App\DAL\DeckRepository;
use App\DAL\UserRepository;

class CardService
{
    public function __construct(
        private readonly CardRepository $cards,
        private readonly UserRepository $users,
        private readonly DeckRepository $decks
    ) {
    }

    public function search(?string $number, ?string $rarity): array
    {
        $results = $this->cards->all();

        if ($number !== null && $number !== '') {
            $n = (int)$number;
            $results = array_filter($results, fn (array $card) => (int)$card['number'] === $n);
        }

        if ($rarity !== null && $rarity !== '') {
            $results = array_filter($results, fn (array $card) => strcasecmp($card['rarity'], $rarity) === 0);
        }

        usort($results, fn (array $a, array $b) => $a['number'] <=> $b['number']);
        return array_values($results);
    }

    public function getDeck(string $username): array
    {
        $deck = $this->decks->findByUsername($username);
        if ($deck === null) {
            return [];
        }

        $allCards = $this->cards->all();
        $byId = [];
        foreach ($allCards as $card) {
            $byId[(int)$card['id']] = $card;
        }

        $owned = [];
        foreach ($deck['cardIds'] as $cardId) {
            if (isset($byId[(int)$cardId])) {
                $owned[] = $byId[(int)$cardId];
            }
        }

        usort($owned, fn (array $a, array $b) => $a['number'] <=> $b['number']);
        return $owned;
    }

    public function claimDailyCard(string $username): array
    {
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        $today = date('Y-m-d');
        $alreadyClaimed = $user['lastClaimDate'] === $today;
        if ($alreadyClaimed) {
            throw new \RuntimeException('Carte déjà récupérée aujourd’hui.');
        }

        $card = $this->pickRandomCard();

        $users = $this->users->all();
        foreach ($users as &$entry) {
            if (strcasecmp($entry['username'], $username) === 0) {
                $entry['lastClaimDate'] = $today;
                break;
            }
        }
        $this->users->saveAll($users);

        $decks = $this->decks->all();
        foreach ($decks as &$deck) {
            if (strcasecmp($deck['username'], $username) === 0) {
                $deck['cardIds'][] = (int)$card['id'];
                break;
            }
        }
        $this->decks->saveAll($decks);

        return $card;
    }

    public function adminOverview(): array
    {
        $users = $this->users->all();
        $decks = $this->decks->all();

        $deckMap = [];
        foreach ($decks as $deck) {
            $deckMap[strtolower($deck['username'])] = $deck['cardIds'];
        }

        foreach ($users as &$user) {
            $user['cardIds'] = $deckMap[strtolower($user['username'])] ?? [];
        }

        return $users;
    }

    public function adminAction(string $action, string $username, array $payload): array
    {
        $users = $this->users->all();
        $decks = $this->decks->all();
        $generatedPassword = null;

        foreach ($users as &$user) {
            if (strcasecmp($user['username'], $username) !== 0) {
                continue;
            }

            switch ($action) {
                case 'setDailyLimit':
                    $user['dailyLimit'] = max(1, (int)($payload['dailyLimit'] ?? 1));
                    break;
                case 'resetDailyClaim':
                    $user['lastClaimDate'] = null;
                    break;
                case 'regenPassword':
                    $generatedPassword = bin2hex(random_bytes(4)) . 'Z!';
                    $user['passwordHash'] = password_hash($generatedPassword, PASSWORD_DEFAULT);
                    break;
            }
        }

        foreach ($decks as &$deck) {
            if (strcasecmp($deck['username'], $username) !== 0) {
                continue;
            }

            if ($action === 'addCard') {
                $cardId = (int)($payload['cardId'] ?? 0);
                if ($this->cards->findById($cardId) !== null) {
                    $deck['cardIds'][] = $cardId;
                }
            }

            if ($action === 'removeCard') {
                $cardId = (int)($payload['cardId'] ?? 0);
                $removed = false;
                $deck['cardIds'] = array_values(array_filter($deck['cardIds'], function (int $id) use ($cardId, &$removed) {
                    if (!$removed && $id === $cardId) {
                        $removed = true;
                        return false;
                    }
                    return true;
                }));
            }
        }

        $this->users->saveAll($users);
        $this->decks->saveAll($decks);

        return ['ok' => true, 'generatedPassword' => $generatedPassword];
    }

    private function pickRandomCard(): array
    {
        $cards = $this->cards->all();
        if ($cards === []) {
            throw new \RuntimeException('Aucune carte disponible.');
        }

        $weights = ['Common' => 70, 'Rare' => 20, 'Epic' => 8, 'Legendary' => 2];
        $pool = [];

        foreach ($cards as $card) {
            $weight = $weights[$card['rarity']] ?? 1;
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $card;
            }
        }

        return $pool[array_rand($pool)];
    }
}
