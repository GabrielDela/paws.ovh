<?php

declare(strict_types=1);

namespace App\DAL;

class UserRepository
{
    private const FILE = 'users.json';

    public function __construct(private readonly JsonStorage $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read(self::FILE);
    }

    public function findByUsername(string $username): ?array
    {
        foreach ($this->all() as $user) {
            if (strcasecmp($user['username'], $username) === 0) {
                return $user;
            }
        }

        return null;
    }

    public function saveAll(array $users): void
    {
        $this->storage->write(self::FILE, array_values($users));
    }

    public function updateOne(callable $callback): void
    {
        $users = $this->all();
        $callback($users);
        $this->saveAll($users);
    }
}
