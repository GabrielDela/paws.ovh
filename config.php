<?php
// ─── Configuration ────────────────────────────────────────────────────────────
define('UPLOAD_FOLDER', __DIR__ . '/public');
define('ADMIN_TOKEN',   getenv('PAWS_ADMIN_TOKEN') ?: 'TOKEN_NOT_SET');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('VOTES_FILE',          __DIR__ . '/votes.json');
define('VOTE_EVENTS_FILE',    __DIR__ . '/vote_events.json');
define('VOTE_PROFILES_FILE',  __DIR__ . '/vote_profiles.json');
define('SETTINGS_FILE',       __DIR__ . '/settings.json');
define('STORAGE_LOCK_FILE',   __DIR__ . '/storage.lock');
define('AWAITING_FILE',       __DIR__ . '/awaiting.json');
define('AWAITING_FOLDER',     __DIR__ . '/public/awaiting');
define('MAX_AWAITING',        50);
define('FINGERPRINT_COOKIE_NAME', 'paws_vid');
define('FINGERPRINT_RETENTION_DAYS', 90);
// Auto-detect base URL: use the request host so it works on localhost and paws.ovh alike.
// Falls back to https://paws.ovh if called from CLI (e.g. cron, tests).
define('BASE_URL', (function () {
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return 'https://paws.ovh';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST']; // includes port if non-standard, e.g. localhost:8080
    return $scheme . '://' . $host;
})());

// ─── Visitor Fingerprint ─────────────────────────────────────────────────────
/**
 * Vote reliability strategy:
 *   1. A persistent cookie nonce (paws_vid) is set on first visit (1 year expiry)
 *   2. The voter token = SHA256( salt + IP + cookie_nonce )
 *
 * This means:
 *   - Changing IP alone won't let someone vote twice (cookie differs)
 *   - Clearing cookies alone won't work (IP check still present)
 *   - Both must match to be recognised as the same voter
 *
 * Note: this is a best-effort approach for a public gallery; it is not
 * cryptographically guaranteed but stops casual abuse.
 */
require_once __DIR__ . '/src/fingerprint.php';

// ─── Alphabet ─────────────────────────────────────────────────────────────────
function getAlphabet(): array
{
    return array_merge(range('A', 'Z'), ['*', 'TOP']);
}

// ─── Vote Storage ────────────────────────────────────────────────────────────
function loadVotes(): array
{
    if (!file_exists(VOTES_FILE)) return [];

    $raw  = file_get_contents(VOTES_FILE);
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];

    $map = [];
    foreach ($data as $entry) {
        if (empty($entry['username'])) continue;
        $u    = $entry['username'];
        $up   = array_values(array_unique(array_filter((array)($entry['votesUp']   ?? []), 'is_string')));
        $down = array_values(array_unique(array_filter((array)($entry['votesDown'] ?? []), 'is_string')));
        // A token cannot appear in both directions – up wins on conflict
        $down = array_values(array_diff($down, $up));
        $map[$u] = ['username' => $u, 'votesUp' => $up, 'votesDown' => $down];
    }

    return array_values($map);
}

function saveVotes(array $votes): void
{
    file_put_contents(VOTES_FILE, json_encode($votes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function findOrCreateVoteEntry(array &$votes, string $username): int
{
    foreach ($votes as $i => $entry) {
        if ($entry['username'] === $username) return $i;
    }
    $votes[] = ['username' => $username, 'votesUp' => [], 'votesDown' => []];
    return count($votes) - 1;
}

// ─── Image Helpers ───────────────────────────────────────────────────────────
function allowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

/**
 * Returns enriched image list with up/down vote counts and the current visitor's vote.
 *
 * @param string $letter 'A'–'Z' | '*' (all) | 'TOP' (sorted by score)
 * @param string $search Partial username match
 */
function getImages(string $letter = '*', string $search = ''): array
{
    if (!is_dir(UPLOAD_FOLDER)) return [];

    $exts   = allowedExtensions();
    $token  = getVoterToken();
    $votes  = loadVotes();

    // Build fast lookup by username
    $votemap = [];
    foreach ($votes as $entry) {
        $u           = $entry['username'];
        $up          = $entry['votesUp']   ?? [];
        $down        = $entry['votesDown'] ?? [];
        $votemap[$u] = [
            'votesUp'   => count($up),
            'votesDown' => count($down),
            'score'     => count($up) - count($down),
            'userVote'  => in_array($token, $up, true) ? 'up'
                         : (in_array($token, $down, true) ? 'down' : null),
        ];
    }

    $images = [];
    foreach (scandir(UPLOAD_FOLDER) as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts)) continue;

        $username  = pathinfo($file, PATHINFO_FILENAME);
        $firstChar = strtolower($username[0] ?? '');

        if ($letter !== '*' && $letter !== 'TOP') {
            if ($firstChar !== strtolower($letter)) continue;
        }
        if ($search !== '' && stripos($username, $search) === false) continue;

        $vm = $votemap[$username] ?? ['votesUp' => 0, 'votesDown' => 0, 'score' => 0, 'userVote' => null];

        $images[] = [
            'username'  => $username,
            'ext'       => $ext,
            'votesUp'   => $vm['votesUp'],
            'votesDown' => $vm['votesDown'],
            'score'     => $vm['score'],
            'userVote'  => $vm['userVote'],
            'url'       => BASE_URL . '/public/' . rawurlencode($file),
            'thumbnail' => BASE_URL . '/public/thumbnail/' . rawurlencode(pathinfo($file, PATHINFO_FILENAME) . '.png'),
        ];
    }

    if ($letter === 'TOP') {
        usort($images, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcasecmp($a['username'], $b['username']));
    } else {
        usort($images, fn($a, $b) => strcasecmp($a['username'], $b['username']));
    }

    return $images;
}

// ─── Thumbnail ───────────────────────────────────────────────────────────────
function createThumbnail(string $source, string $dest, int $maxW = 300, int $maxH = 300): bool
{
    // GD not available: just copy the original so uploads still work
    if (!extension_loaded('gd')) {
        $thumbDir = dirname($dest);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
        return copy($source, $dest);
    }

    $info = @getimagesize($source);
    if (!$info) return false;

    [$origW, $origH, $type] = $info;

    // Guard against images that would exhaust memory.
    // A 32-bit RGBA pixel = 4 bytes. Add 20% margin.
    $estimatedBytes = $origW * $origH * 4 * 1.2;
    $memoryLimit    = return_bytes_cfg(ini_get('memory_limit'));
    $memoryUsed     = memory_get_usage(true);
    if ($memoryLimit > 0 && ($memoryUsed + $estimatedBytes) > $memoryLimit) {
        // Temporarily bump memory just for this image
        ini_set('memory_limit', ceil(($memoryUsed + $estimatedBytes) / 1024 / 1024 + 64) . 'M');
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($source),
        IMAGETYPE_PNG  => imagecreatefrompng($source),
        IMAGETYPE_GIF  => imagecreatefromgif($source),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false,
        default        => false,
    };
    if (!$src) return false;

    $ratio = min($maxW / $origW, $maxH / $origH, 1.0);
    $newW  = max(1, (int) round($origW * $ratio));
    $newH  = max(1, (int) round($origH * $ratio));

    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $thumbDir = dirname($dest);
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

    $ok = imagepng($dst, $dest, 6); // compression 6 = good balance speed/size
    // Always free GD resources immediately to avoid memory pressure on bulk uploads
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

function return_bytes_cfg(string $val): int
{
    $val  = trim($val);
    if ($val === '-1') return -1;
    $last = strtolower($val[strlen($val) - 1]);
    $num  = (int) $val;
    return match($last) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

// ─── Awaiting Submissions ───────────────────────────────────────────────────
function loadAwaiting(): array
{
    if (!file_exists(AWAITING_FILE)) return [];
    $data = json_decode(file_get_contents(AWAITING_FILE), true);
    return is_array($data) ? $data : [];
}

function saveAwaiting(array $entries): void
{
    file_put_contents(AWAITING_FILE, json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getSubmitterToken(): string
{
    return hash('sha256', 'paws-submit-v1-' . getClientIp());
}
