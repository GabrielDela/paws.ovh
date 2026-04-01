<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$letter = preg_replace('/[^a-zA-Z*]/', '', $_GET['letter'] ?? '*') ?: '*';
$limit  = max(1, min((int)($_GET['limit'] ?? 50), 100));

$images = getImages($letter);

// Public API: strip internal fields, add score
$output = array_slice(array_map(fn($img) => [
    'username'  => $img['username'],
    'votesUp'   => $img['votesUp'],
    'votesDown' => $img['votesDown'],
    'score'     => $img['score'],
    'url'       => $img['url'],
    'thumbnail' => $img['thumbnail'],
], $images), 0, $limit);

// Sort by score descending
usort($output, fn($a, $b) => $b['score'] <=> $a['score']);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
