<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/images.php';

header('Content-Type: application/json');

$letter = $_GET['letter'] ?? '*';
$limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 100)) : 50; // default 50, max 100

$allImages = getImages($letter);

// Remove 'userVoted' from each entry
$allImages = array_map(function ($entry) {
    $entry['url'] = 'https://paws.ovh/public/' . $entry['username'] . '.png';
    $entry['thumbnail'] = 'https://paws.ovh/public/thumbnail/' . $entry['username'] . '.png';
    unset($entry['userVoted']);
    return $entry;
}, $allImages);

// Sort by (votesUp - votesDown) descending
usort($allImages, function ($a, $b) {
    $netA = $a['votesUp'] - $a['votesDown'];
    $netB = $b['votesUp'] - $b['votesDown'];
    return $netB <=> $netA;
});

// Limit the results
$limited = array_slice($allImages, 0, $limit);

echo json_encode(array_values($limited));
