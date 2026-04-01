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

$username  = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $body['username'] ?? '');
$direction = $body['direction'] ?? 'up';

if (!$username || !in_array($direction, ['up', 'down'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$response = withStorageLock(static function () use ($username, $direction) {
    $settings    = loadSettings();
    $mode        = $settings['voteSecurityMode'];
    $fingerprint = collectVoteFingerprint();
    $votes       = loadVotes();
    $events      = pruneVoteEvents(loadVoteEvents(), $settings['retentionDays']);
    $profiles    = rebuildVoteProfiles($events);
    $idx         = findOrCreateVoteEntry($votes, $username);
    $entry       = &$votes[$idx];

    $up         = &$entry['votesUp'];
    $down       = &$entry['votesDown'];
    $cookieHash = $fingerprint['cookieHash'];

    $alreadyUp   = in_array($cookieHash, $up, true);
    $alreadyDown = in_array($cookieHash, $down, true);
    $action      = 'added';
    $outcome     = 'allowed';
    $error       = null;
    $match       = [
        'level'               => 'none',
        'matchedSignals'      => [],
        'flags'               => [],
        'candidateCookieHash' => null,
        'candidateCookieMask' => null,
    ];
    $flags = getFingerprintDriftFlags($fingerprint, $profiles);

    if ($direction === 'up') {
        if ($alreadyUp) {
            $up      = array_values(array_filter($up, static fn($value) => $value !== $cookieHash));
            $action  = 'removed';
            $outcome = 'removed';
        } elseif ($alreadyDown) {
            $down    = array_values(array_filter($down, static fn($value) => $value !== $cookieHash));
            $up[]    = $cookieHash;
            $action  = 'added';
            $outcome = 'allowed';
        } else {
            $match = findStrongestVoteMatchForEntry($fingerprint, $entry, $profiles);
            $flags = array_values(array_unique(array_merge($flags, $match['flags'])));

            if (shouldBlockVoteMatch($mode, $match['level'])) {
                $action  = 'blocked';
                $outcome = 'blocked';
                $error   = buildVoteBlockedMessage($mode, $match['level']);
            } else {
                $up[]    = $cookieHash;
            }
        }
    } else {
        if ($alreadyDown) {
            $down    = array_values(array_filter($down, static fn($value) => $value !== $cookieHash));
            $action  = 'removed';
            $outcome = 'removed';
        } elseif ($alreadyUp) {
            $up      = array_values(array_filter($up, static fn($value) => $value !== $cookieHash));
            $down[]  = $cookieHash;
            $action  = 'added';
            $outcome = 'allowed';
        } else {
            $match = findStrongestVoteMatchForEntry($fingerprint, $entry, $profiles);
            $flags = array_values(array_unique(array_merge($flags, $match['flags'])));

            if (shouldBlockVoteMatch($mode, $match['level'])) {
                $action  = 'blocked';
                $outcome = 'blocked';
                $error   = buildVoteBlockedMessage($mode, $match['level']);
            } else {
                $down[]  = $cookieHash;
            }
        }
    }

    if ($outcome !== 'blocked') {
        $up   = array_values(array_unique(array_filter($up, 'is_string')));
        $down = array_values(array_unique(array_filter($down, 'is_string')));
        $down = array_values(array_diff($down, $up));
        saveVotes($votes);
    }

    $events[] = [
        'id'                => uniqid('evt_', true),
        'timestamp'         => nowIso8601(),
        'username'          => $username,
        'direction'         => $direction,
        'action'            => $action,
        'outcome'           => $outcome,
        'mode'              => $mode,
        'matchLevel'        => $match['level'],
        'matchedSignals'    => $match['matchedSignals'],
        'matchedCookieHash' => $match['candidateCookieHash'],
        'matchedCookieMask' => $match['candidateCookieMask'],
        'flags'             => $flags,
        'signals'           => $fingerprint,
    ];

    $events = pruneVoteEvents($events, $settings['retentionDays']);
    saveVoteEvents($events);
    saveVoteProfiles(rebuildVoteProfiles($events));

    $userVote = in_array($cookieHash, $up, true) ? 'up' : (in_array($cookieHash, $down, true) ? 'down' : null);

    return [
        'success'       => $outcome !== 'blocked',
        'error'         => $error,
        'action'        => $action,
        'direction'     => $direction,
        'username'      => $username,
        'votesUp'       => count($up),
        'votesDown'     => count($down),
        'score'         => count($up) - count($down),
        'userVote'      => $userVote,
        'securityMode'  => $mode,
        'matchLevel'    => $match['level'],
        'matchedCookie' => $match['candidateCookieMask'],
    ];
});

if (!$response['success']) {
    http_response_code(409);
}

echo json_encode($response);
