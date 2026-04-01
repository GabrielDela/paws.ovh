<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 'direction' must be 'up' or 'down'
$username  = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $body['username']  ?? '');
$direction = $body['direction'] ?? 'up';

if (!$username || !in_array($direction, ['up', 'down'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$token = getVoterToken();
$votes = loadVotes();
$idx   = findOrCreateVoteEntry($votes, $username);
$entry = &$votes[$idx];

$up   = &$entry['votesUp'];
$down = &$entry['votesDown'];

$alreadyUp   = in_array($token, $up,   true);
$alreadyDown = in_array($token, $down, true);

if ($direction === 'up') {
    if ($alreadyUp) {
        // Toggle off
        $up     = array_values(array_filter($up, fn($v) => $v !== $token));
        $action = 'removed';
    } else {
        // Remove any existing down vote, add up vote
        $down   = array_values(array_filter($down, fn($v) => $v !== $token));
        $up[]   = $token;
        $action = 'added';
    }
} else {
    if ($alreadyDown) {
        // Toggle off
        $down   = array_values(array_filter($down, fn($v) => $v !== $token));
        $action = 'removed';
    } else {
        // Remove any existing up vote, add down vote
        $up     = array_values(array_filter($up,   fn($v) => $v !== $token));
        $down[] = $token;
        $action = 'added';
    }
}

saveVotes($votes);

echo json_encode([
    'success'   => true,
    'action'    => $action,
    'direction' => $direction,
    'username'  => $username,
    'votesUp'   => count($up),
    'votesDown' => count($down),
    'score'     => count($up) - count($down),
    'userVote'  => in_array($token, $up, true) ? 'up' : (in_array($token, $down, true) ? 'down' : null),
]);
