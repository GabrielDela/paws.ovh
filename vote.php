<?php

// vote.php
require_once 'config.php';

define('MAX_USERNAME_LENGTH', 50);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$dataJson = file_get_contents('php://input');
$data = json_decode($dataJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$username = preg_replace('/[^a-zA-Z0-9-_]/', '_', $data['username'] ?? '');
    
$IP = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';

$userToken = hash('sha256', 'paws-ip-token-salt-' . $IP);

$direction = $data['direction'] ?? '';

if (!$username || !in_array($direction, ['up', 'down'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$imageExists = false;
foreach ($allowedExtensions as $ext) {
    if (file_exists(UPLOAD_FOLDER . '/' . $username . '.' . $ext)) {
        $imageExists = true;
        break;
    }
}

if (!$imageExists || strlen($username) > MAX_USERNAME_LENGTH) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid username']);
    exit;
}

$votesFile = __DIR__ . '/votes.json';

// Make sure the votes.json file exists, create if not
if (!file_exists($votesFile)) {
    file_put_contents($votesFile, json_encode([]));
}

$votesContent = file_get_contents($votesFile);
$votes = json_decode($votesContent, true);

if (!is_array($votes)) {
    $votes = [];
}

// Find index of this image entry
$index = null;
foreach ($votes as $i => $entry) {
    if ($entry['username'] === $username) {
        $index = $i;
        break;
    }
}

if ($index === null) {
    // Create new vote entry
    $votes[] = [
        'username' => $username,
        'votesUp' => [],
        'votesDown' => []
    ];
    // PHP 7.3+ function, fallback for older versions below:
    if (function_exists('array_key_last')) {
        $index = array_key_last($votes);
    } else {
        $index = count($votes) - 1;
    }
}

// Remove any previous votes from this IP token
$votes[$index]['votesUp'] = array_filter($votes[$index]['votesUp'], fn($v) => $v !== $userToken);
$votes[$index]['votesDown'] = array_filter($votes[$index]['votesDown'], fn($v) => $v !== $userToken);

// Add new vote
if ($direction === 'up') {
    $votes[$index]['votesUp'][] = $userToken;
} else {
    $votes[$index]['votesDown'][] = $userToken;
}

file_put_contents($votesFile, json_encode($votes, JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);
