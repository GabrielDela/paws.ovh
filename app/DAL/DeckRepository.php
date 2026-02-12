<?php

declare(strict_types=1);

namespace App\DAL;

class DeckRepository
{
    private const FILE = 'decks.json';

    public function __construct(private readonly JsonStorage $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read(self::FILE);
    }

    public function findByUsername(string $username): ?array
    {
        foreach ($this->all() as $deck) {
            if (strcasecmp($deck['username'], $username) === 0) {
                return $deck;
            }
        }
        return null;
    }

    public function saveAll(array $decks): void
    {
        $this->storage->write(self::FILE, array_values($decks));
    }
}
