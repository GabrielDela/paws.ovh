<?php

declare(strict_types=1);

namespace App\DAL;

/**
 * Generic JSON file storage helper with file locking.
 */
class JsonStorage
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function read(string $file): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function write(string $file, array $payload): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . $file;
        $fp = fopen($path, 'c+');

        if ($fp === false) {
            throw new \RuntimeException("Unable to open storage file: {$file}");
        }

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
