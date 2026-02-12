<?php

declare(strict_types=1);

namespace App\DAL;

class CardRepository
{
    private const FILE = 'cards.json';

    public function __construct(private readonly JsonStorage $storage)
    {
    }

    public function all(): array
    {
        $cards = $this->storage->read(self::FILE);
        usort($cards, fn (array $a, array $b) => $a['number'] <=> $b['number']);
        return $cards;
    }

    public function findById(int $id): ?array
    {
        foreach ($this->all() as $card) {
            if ((int)$card['id'] === $id) {
                return $card;
            }
        }
        return null;
    }
}
