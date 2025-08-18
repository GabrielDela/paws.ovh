<?php
// config.php

define('UPLOAD_FOLDER', __DIR__ . '/public');
define('ADMIN_TOKEN', getenv('ADMIN_TOKEN') ?: '');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Start session for CSRF protection
session_start();

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getAlphabet(): array
{
    return array_merge(range('A', 'Z'), ['*', 'TOP']);
}

function getClientIp(): string {
    return $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
}

function getIpToken(): string {
    return hash('sha256', 'paws-ip-token-salt-' . getClientIp());
}

function getImages(string $letter = 'A', string $search = ''): array
{
    $files = array_filter(scandir(UPLOAD_FOLDER), function ($file) {
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
    });

    $usernames = array_map(function ($file) {
        if (preg_match('/^([a-zA-Z0-9-_]+)\.(jpg|jpeg|png|gif|webp)$/i', $file, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }, $files);

    $usernames = array_unique($usernames);

    $votesFile = __DIR__ . '/votes.json';
    $votesData = file_exists($votesFile) ? json_decode(file_get_contents($votesFile), true) : [];

    $currentToken = getIpToken();

    $result = [];

    foreach ($usernames as $username) {
        $entry = [
            'username' => $username,
            'votesUp' => 0,
            'votesDown' => 0,
            'userVoted' => null,
        ];

        foreach ($votesData as $vote) {
            if ($vote['username'] === $username) {
                $entry['votesUp'] = count($vote['votesUp']);
                $entry['votesDown'] = count($vote['votesDown']);
                if ($currentToken) {
                    if (in_array($currentToken, $vote['votesUp'])) {
                        $entry['userVoted'] = 'up';
                    } elseif (in_array($currentToken, $vote['votesDown'])) {
                        $entry['userVoted'] = 'down';
                    }
                }
                break;
            }
        }

        $result[] = $entry;
    }

    // ✅ Enforce exclusivity: priority is search > TOP > letter
    if ($search !== '') {
        $result = array_filter(
            $result,
            fn($img) => stripos($img['username'], $search) !== false
        );
        usort($result, fn($a, $b) => strcasecmp($a['username'], $b['username']));
    } elseif ($letter === 'TOP') {
        usort($result, fn($a, $b) =>
            ($b['votesUp'] - $b['votesDown']) <=> ($a['votesUp'] - $a['votesDown'])
        );
    } elseif ($letter !== '*') {
        $result = array_filter(
            $result,
            fn($img) => strtoupper($img['username'][0]) === $letter && $img['username'] !== 'unknown'
        );
        usort($result, fn($a, $b) => strcasecmp($a['username'], $b['username']));
    } else {
        usort($result, fn($a, $b) => strcasecmp($a['username'], $b['username']));
    }

    return array_values($result);
}

function getNextUnvotedImage(): ?array
{
    $images = getImages('*');
    foreach ($images as $img) {
        if ($img['userVoted'] === null && $img['username'] !== 'unknown') {
            return $img;
        }
    }
    return null;
}