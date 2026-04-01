<?php

if (!defined('FINGERPRINT_SECURITY_MODES')) {
    define('FINGERPRINT_SECURITY_MODES', ['review', 'balanced', 'strict']);
}

if (!defined('FINGERPRINT_CLIENT_HINT_HEADERS')) {
    define('FINGERPRINT_CLIENT_HINT_HEADERS', [
        'HTTP_SEC_CH_UA'                   => 'ua',
        'HTTP_SEC_CH_UA_FULL_VERSION_LIST' => 'uaFullVersionList',
        'HTTP_SEC_CH_UA_PLATFORM'          => 'platform',
        'HTTP_SEC_CH_UA_PLATFORM_VERSION'  => 'platformVersion',
        'HTTP_SEC_CH_UA_MOBILE'            => 'mobile',
        'HTTP_SEC_CH_UA_ARCH'              => 'arch',
        'HTTP_SEC_CH_UA_BITNESS'           => 'bitness',
        'HTTP_SEC_CH_UA_MODEL'             => 'model',
    ]);
}

function sendFingerprintHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header(
        'Accept-CH: Sec-CH-UA, Sec-CH-UA-Full-Version-List, Sec-CH-UA-Platform, ' .
        'Sec-CH-UA-Platform-Version, Sec-CH-UA-Mobile, Sec-CH-UA-Arch, ' .
        'Sec-CH-UA-Bitness, Sec-CH-UA-Model'
    );
}

function withStorageLock(callable $callback)
{
    $lockHandle = fopen(STORAGE_LOCK_FILE, 'c+');
    if ($lockHandle === false) {
        return $callback();
    }

    try {
        flock($lockHandle, LOCK_EX);
        return $callback();
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function loadJsonFile(string $path, $default)
{
    if (!file_exists($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function saveJsonFile(string $path, $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function getFingerprintSecret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    $envSecret = trim((string) getenv('PAWS_FINGERPRINT_SECRET'));
    if ($envSecret !== '') {
        $secret = $envSecret;
        return $secret;
    }

    $secret = hash('sha256', __DIR__ . '|' . ADMIN_TOKEN . '|paws-fingerprint-v1');
    return $secret;
}

function hashFingerprintValue(string $namespace, string $value): string
{
    return hash_hmac('sha256', $namespace . '|' . $value, getFingerprintSecret());
}

function nowIso8601(): string
{
    return gmdate('c');
}

function parseIsoTimestamp(?string $timestamp): int
{
    if (!is_string($timestamp) || trim($timestamp) === '') {
        return 0;
    }

    $parsed = strtotime($timestamp);
    return $parsed === false ? 0 : $parsed;
}

function normalizeVoteSecurityMode(?string $mode): string
{
    $mode = strtolower(trim((string) $mode));
    return in_array($mode, FINGERPRINT_SECURITY_MODES, true) ? $mode : 'review';
}

function getDefaultSettings(): array
{
    return [
        'voteSecurityMode' => 'review',
        'retentionDays'    => FINGERPRINT_RETENTION_DAYS,
    ];
}

function loadSettings(): array
{
    $data = loadJsonFile(SETTINGS_FILE, []);
    if (!is_array($data)) {
        $data = [];
    }

    $settings = array_merge(getDefaultSettings(), $data);
    $settings['voteSecurityMode'] = normalizeVoteSecurityMode($settings['voteSecurityMode'] ?? 'review');

    $retentionDays = (int) ($settings['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS);
    $settings['retentionDays'] = max(7, min($retentionDays, 365));

    return $settings;
}

function saveSettings(array $settings): void
{
    $current = loadSettings();
    $merged  = array_merge($current, $settings);

    $payload = [
        'voteSecurityMode' => normalizeVoteSecurityMode($merged['voteSecurityMode'] ?? 'review'),
        'retentionDays'    => max(7, min((int) ($merged['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS), 365)),
    ];

    saveJsonFile(SETTINGS_FILE, $payload);
}

function getVoteSecurityMode(): string
{
    $settings = loadSettings();
    return $settings['voteSecurityMode'];
}

function isAdminTokenValid(string $token): bool
{
    return $token !== '' && hash_equals(ADMIN_TOKEN, $token);
}

function getClientIp(): string
{
    $cloudflareIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cloudflareIp !== '' && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
        return $cloudflareIp;
    }

    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    $clientIp = trim((string) ($_SERVER['HTTP_CLIENT_IP'] ?? ''));
    if ($clientIp !== '' && filter_var($clientIp, FILTER_VALIDATE_IP)) {
        return $clientIp;
    }

    return '0.0.0.0';
}

function shouldUseSecureCookies(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = explode(':', $host)[0] ?? $host;

    if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }

    return true;
}

function getVisitorCookieValue(): string
{
    if (empty($_COOKIE[FINGERPRINT_COOKIE_NAME])) {
        $cookieValue = bin2hex(random_bytes(16));
        setcookie(FINGERPRINT_COOKIE_NAME, $cookieValue, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => shouldUseSecureCookies(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[FINGERPRINT_COOKIE_NAME] = $cookieValue;
    }

    return (string) $_COOKIE[FINGERPRINT_COOKIE_NAME];
}

function maskCookieValue(string $value): string
{
    $length = strlen($value);
    if ($length <= 8) {
        return str_repeat('*', max(1, $length));
    }

    return substr($value, 0, 4) . '...' . substr($value, -4);
}

function maskIp(string $ip): string
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'unknown';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = 'x';
        return implode('.', $parts);
    }

    $packed = inet_pton($ip);
    if ($packed === false) {
        return 'unknown';
    }

    $segments = unpack('n8', $packed);
    if (!is_array($segments)) {
        return 'unknown';
    }

    return sprintf('%x:%x:%x:%x::', $segments[1], $segments[2], $segments[3], $segments[4]);
}

function getIpPrefix(string $ip): string
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
    }

    $packed = inet_pton($ip);
    if ($packed === false) {
        return '';
    }

    $segments = unpack('n8', $packed);
    if (!is_array($segments)) {
        return '';
    }

    return sprintf('%x:%x:%x:%x::/64', $segments[1], $segments[2], $segments[3], $segments[4]);
}

function maskIpPrefix(string $prefix): string
{
    if (str_contains($prefix, '/24')) {
        return str_replace('.0/24', '.x/24', $prefix);
    }

    if (str_contains($prefix, '/64')) {
        return $prefix;
    }

    return 'unknown';
}

function normalizeHeaderValue(?string $value, bool $lowercase = true): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $lowercase ? strtolower($value) : $value;
}

function normalizeUserAgent(string $userAgent): array
{
    $userAgent = trim($userAgent);
    $lowerUa   = strtolower($userAgent);

    $family = 'Unknown';
    $major  = '';

    $patterns = [
        '/edg\/(\d+)/i'             => 'Edge',
        '/opr\/(\d+)/i'             => 'Opera',
        '/samsungbrowser\/(\d+)/i'  => 'Samsung Internet',
        '/firefox\/(\d+)/i'         => 'Firefox',
        '/chrome\/(\d+)/i'          => 'Chrome',
        '/version\/(\d+).+safari/i' => 'Safari',
        '/curl\/(\d+)/i'            => 'curl',
    ];

    foreach ($patterns as $pattern => $candidateFamily) {
        if (preg_match($pattern, $userAgent, $matches)) {
            $family = $candidateFamily;
            $major  = $matches[1] ?? '';
            break;
        }
    }

    $platform = 'Unknown';
    if (str_contains($lowerUa, 'android')) {
        $platform = 'Android';
    } elseif (str_contains($lowerUa, 'iphone') || str_contains($lowerUa, 'ipad') || str_contains($lowerUa, 'cpu iphone os')) {
        $platform = 'iOS';
    } elseif (str_contains($lowerUa, 'windows')) {
        $platform = 'Windows';
    } elseif (str_contains($lowerUa, 'macintosh') || str_contains($lowerUa, 'mac os x')) {
        $platform = 'macOS';
    } elseif (str_contains($lowerUa, 'cros')) {
        $platform = 'ChromeOS';
    } elseif (str_contains($lowerUa, 'linux')) {
        $platform = 'Linux';
    }

    $device = 'Desktop';
    if (str_contains($lowerUa, 'ipad') || str_contains($lowerUa, 'tablet')) {
        $device = 'Tablet';
    } elseif (str_contains($lowerUa, 'mobile') || str_contains($lowerUa, 'iphone') || str_contains($lowerUa, 'android')) {
        $device = 'Mobile';
    }

    $labelParts = array_filter([
        trim($family . ($major !== '' ? ' ' . $major : '')),
        $platform,
        $device,
    ]);

    return [
        'normalized' => strtolower(implode('|', [$family, $major, $platform, $device])),
        'label'      => implode(' / ', $labelParts),
        'family'     => $family,
        'major'      => $major,
        'platform'   => $platform,
        'device'     => $device,
    ];
}

function collectClientHints(): array
{
    $hints = [];

    foreach (FINGERPRINT_CLIENT_HINT_HEADERS as $serverKey => $hintKey) {
        $value = normalizeHeaderValue($_SERVER[$serverKey] ?? null);
        if ($value !== '') {
            $hints[$hintKey] = $value;
        }
    }

    ksort($hints);
    return $hints;
}

function buildClientHintsLabel(array $hints): ?string
{
    if ($hints === []) {
        return null;
    }

    $parts = [];
    foreach ($hints as $key => $value) {
        $parts[] = $key . '=' . $value;
    }

    return implode(', ', $parts);
}

function collectVoteFingerprint(): array
{
    $cookieValue      = getVisitorCookieValue();
    $clientIp         = getClientIp();
    $ipPrefix         = getIpPrefix($clientIp);
    $rawUserAgent     = normalizeHeaderValue($_SERVER['HTTP_USER_AGENT'] ?? '', false);
    $normalizedUa     = normalizeUserAgent($rawUserAgent);
    $acceptLanguage   = normalizeHeaderValue($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $acceptEncoding   = normalizeHeaderValue($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
    $clientHints      = collectClientHints();
    $clientHintsLabel = buildClientHintsLabel($clientHints);
    $country          = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    $country          = preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
    $dnt              = trim((string) ($_SERVER['HTTP_DNT'] ?? ''));
    $gpc              = trim((string) ($_SERVER['HTTP_SEC_GPC'] ?? ''));

    return [
        'cookieHash'          => hashFingerprintValue('cookie', $cookieValue),
        'cookieMask'          => maskCookieValue($cookieValue),
        'ipHash'              => hashFingerprintValue('ip', $clientIp),
        'ipMask'              => maskIp($clientIp),
        'ipPrefixHash'        => $ipPrefix !== '' ? hashFingerprintValue('ip-prefix', $ipPrefix) : null,
        'ipPrefixMask'        => $ipPrefix !== '' ? maskIpPrefix($ipPrefix) : null,
        'uaHash'              => $normalizedUa['normalized'] !== '' ? hashFingerprintValue('ua', $normalizedUa['normalized']) : null,
        'uaLabel'             => $normalizedUa['label'],
        'uaFamily'            => $normalizedUa['family'],
        'uaPlatform'          => $normalizedUa['platform'],
        'uaDevice'            => $normalizedUa['device'],
        'rawUaHash'           => $rawUserAgent !== '' ? hashFingerprintValue('raw-ua', $rawUserAgent) : null,
        'acceptLanguageHash'  => $acceptLanguage !== '' ? hashFingerprintValue('lang', $acceptLanguage) : null,
        'acceptLanguageLabel' => $acceptLanguage !== '' ? $acceptLanguage : null,
        'acceptEncodingHash'  => $acceptEncoding !== '' ? hashFingerprintValue('encoding', $acceptEncoding) : null,
        'acceptEncodingLabel' => $acceptEncoding !== '' ? $acceptEncoding : null,
        'country'             => $country !== '' ? $country : null,
        'dnt'                 => in_array($dnt, ['0', '1'], true) ? $dnt : null,
        'gpc'                 => in_array($gpc, ['0', '1'], true) ? $gpc : null,
        'clientHintsHash'     => $clientHints !== [] ? hashFingerprintValue('client-hints', json_encode($clientHints, JSON_UNESCAPED_SLASHES)) : null,
        'clientHintsLabel'    => $clientHintsLabel,
        'clientHints'         => $clientHints,
    ];
}

function getCurrentVoteIdentityHash(): string
{
    $fingerprint = collectVoteFingerprint();
    return $fingerprint['cookieHash'];
}

function getVoterToken(): string
{
    return getCurrentVoteIdentityHash();
}

function loadVoteEvents(): array
{
    $events = loadJsonFile(VOTE_EVENTS_FILE, []);
    return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
}

function saveVoteEvents(array $events): void
{
    saveJsonFile(VOTE_EVENTS_FILE, array_values($events));
}

function loadVoteProfiles(): array
{
    $profiles = loadJsonFile(VOTE_PROFILES_FILE, []);
    return is_array($profiles) ? $profiles : [];
}

function saveVoteProfiles(array $profiles): void
{
    ksort($profiles);
    saveJsonFile(VOTE_PROFILES_FILE, $profiles);
}

function pruneVoteEvents(array $events, ?int $retentionDays = null): array
{
    $retentionDays = $retentionDays ?? (loadSettings()['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS);
    $cutoff        = time() - ($retentionDays * 86400);

    return array_values(array_filter($events, static function ($event) use ($cutoff) {
        $timestamp = parseIsoTimestamp($event['timestamp'] ?? null);
        return $timestamp === 0 || $timestamp >= $cutoff;
    }));
}

function touchSignalBucket(array &$bucket, ?string $key, array $payload, string $timestamp): void
{
    if (!is_string($key) || $key === '') {
        return;
    }

    if (!isset($bucket[$key])) {
        $bucket[$key] = array_merge([
            'firstSeen' => $timestamp,
            'lastSeen'  => $timestamp,
            'count'     => 0,
        ], $payload);
    }

    $bucket[$key]['lastSeen'] = $timestamp;
    $bucket[$key]['count']    = (int) ($bucket[$key]['count'] ?? 0) + 1;

    foreach ($payload as $field => $value) {
        if ($value !== null && $value !== '' && empty($bucket[$key][$field])) {
            $bucket[$key][$field] = $value;
        }
    }
}

function rebuildVoteProfiles(array $events): array
{
    usort($events, static function (array $a, array $b): int {
        return parseIsoTimestamp($a['timestamp'] ?? null) <=> parseIsoTimestamp($b['timestamp'] ?? null);
    });

    $profiles          = [];
    $minuteCounts      = [];
    $cookiesByIp       = [];
    $cookiesByUa       = [];
    $lastBlockedByHash = [];

    foreach ($events as $event) {
        $signals    = is_array($event['signals'] ?? null) ? $event['signals'] : [];
        $cookieHash = $signals['cookieHash'] ?? '';
        $timestamp  = (string) ($event['timestamp'] ?? nowIso8601());

        if (!is_string($cookieHash) || $cookieHash === '') {
            continue;
        }

        if (!isset($profiles[$cookieHash])) {
            $profiles[$cookieHash] = [
                'cookieHash'        => $cookieHash,
                'cookieMask'        => $signals['cookieMask'] ?? 'unknown',
                'firstSeen'         => $timestamp,
                'lastSeen'          => $timestamp,
                'lastOutcome'       => $event['outcome'] ?? null,
                'lastMode'          => $event['mode'] ?? null,
                'linkedIps'         => [],
                'linkedPrefixes'    => [],
                'linkedUas'         => [],
                'linkedLanguages'   => [],
                'linkedClientHints' => [],
                'linkedCountries'   => [],
                'stats'             => [
                    'votes'                => 0,
                    'allowed'              => 0,
                    'removed'              => 0,
                    'blocked'              => 0,
                    'blockedAttempts'      => 0,
                    'repeatDenials'        => 0,
                    'burstVotesPerMinute'  => 0,
                    'ipFanout'             => 0,
                    'uaFanout'             => 0,
                    'countryFanout'        => 0,
                    'countryChanges'       => 0,
                    'headerMismatchCount'  => 0,
                    'cookieResetMatches'   => 0,
                    'sharedIpCookieFanout' => 0,
                    'sharedUaCookieFanout' => 0,
                    'uniqueTargets'        => 0,
                ],
                'flagCounts'        => [],
                'recentTargets'     => [],
            ];
        }

        $profile = &$profiles[$cookieHash];
        $profile['lastSeen']    = $timestamp;
        $profile['lastOutcome'] = $event['outcome'] ?? null;
        $profile['lastMode']    = $event['mode'] ?? null;
        $profile['cookieMask']  = $profile['cookieMask'] ?: ($signals['cookieMask'] ?? 'unknown');
        $profile['stats']['votes']++;

        $outcome = (string) ($event['outcome'] ?? 'allowed');
        if (isset($profile['stats'][$outcome])) {
            $profile['stats'][$outcome]++;
        }
        if ($outcome === 'blocked') {
            $profile['stats']['blockedAttempts']++;

            $lastBlocked = $lastBlockedByHash[$cookieHash] ?? 0;
            $currentTs   = parseIsoTimestamp($timestamp);
            if ($lastBlocked > 0 && $currentTs > 0 && ($currentTs - $lastBlocked) <= 900) {
                $profile['stats']['repeatDenials']++;
            }
            $lastBlockedByHash[$cookieHash] = $currentTs;
        }

        $username = (string) ($event['username'] ?? '');
        if ($username !== '') {
            $profile['recentTargets'][$username] = $timestamp;
        }

        touchSignalBucket($profile['linkedIps'], $signals['ipHash'] ?? null, [
            'mask'       => $signals['ipMask'] ?? 'unknown',
            'prefixHash' => $signals['ipPrefixHash'] ?? null,
            'prefixMask' => $signals['ipPrefixMask'] ?? null,
            'country'    => $signals['country'] ?? null,
        ], $timestamp);

        touchSignalBucket($profile['linkedPrefixes'], $signals['ipPrefixHash'] ?? null, [
            'mask' => $signals['ipPrefixMask'] ?? 'unknown',
        ], $timestamp);

        touchSignalBucket($profile['linkedUas'], $signals['uaHash'] ?? null, [
            'label'     => $signals['uaLabel'] ?? 'Unknown',
            'family'    => $signals['uaFamily'] ?? 'Unknown',
            'platform'  => $signals['uaPlatform'] ?? 'Unknown',
            'device'    => $signals['uaDevice'] ?? 'Unknown',
            'rawUaHash' => $signals['rawUaHash'] ?? null,
        ], $timestamp);

        touchSignalBucket($profile['linkedLanguages'], $signals['acceptLanguageHash'] ?? null, [
            'label' => $signals['acceptLanguageLabel'] ?? null,
        ], $timestamp);

        touchSignalBucket($profile['linkedClientHints'], $signals['clientHintsHash'] ?? null, [
            'label' => $signals['clientHintsLabel'] ?? null,
        ], $timestamp);

        touchSignalBucket($profile['linkedCountries'], $signals['country'] ?? null, [
            'label' => $signals['country'] ?? null,
        ], $timestamp);

        $minuteKey = $cookieHash . '|' . gmdate('Y-m-d H:i', parseIsoTimestamp($timestamp) ?: time());
        $minuteCounts[$minuteKey] = ($minuteCounts[$minuteKey] ?? 0) + 1;
        $profile['stats']['burstVotesPerMinute'] = max($profile['stats']['burstVotesPerMinute'], $minuteCounts[$minuteKey]);

        if (!empty($signals['ipHash'])) {
            $cookiesByIp[$signals['ipHash']][$cookieHash] = true;
        }
        if (!empty($signals['uaHash'])) {
            $cookiesByUa[$signals['uaHash']][$cookieHash] = true;
        }

        foreach ((array) ($event['flags'] ?? []) as $flag) {
            if (!is_string($flag) || $flag === '') {
                continue;
            }
            $profile['flagCounts'][$flag] = (int) ($profile['flagCounts'][$flag] ?? 0) + 1;
        }

        unset($profile);
    }

    foreach ($profiles as &$profile) {
        $recentTargets = $profile['recentTargets'];
        arsort($recentTargets);
        $profile['recentTargets'] = array_slice(array_keys($recentTargets), 0, 8);

        $profile['stats']['uniqueTargets']         = count($recentTargets);
        $profile['stats']['ipFanout']              = count($profile['linkedIps']);
        $profile['stats']['uaFanout']              = count($profile['linkedUas']);
        $profile['stats']['countryFanout']         = count($profile['linkedCountries']);
        $profile['stats']['countryChanges']        = max(0, count($profile['linkedCountries']) - 1);
        $profile['stats']['headerMismatchCount']   = max(0, count($profile['linkedUas']) - 1);
        $profile['stats']['cookieResetMatches']    = (int) ($profile['flagCounts']['cookie_reset_suspected'] ?? 0);

        foreach ($profile['linkedIps'] as $ipHash => $ipEntry) {
            $profile['stats']['sharedIpCookieFanout'] = max(
                $profile['stats']['sharedIpCookieFanout'],
                count($cookiesByIp[$ipHash] ?? [])
            );
        }

        foreach ($profile['linkedUas'] as $uaHash => $uaEntry) {
            $profile['stats']['sharedUaCookieFanout'] = max(
                $profile['stats']['sharedUaCookieFanout'],
                count($cookiesByUa[$uaHash] ?? [])
            );
        }
    }
    unset($profile);

    ksort($profiles);
    return $profiles;
}

function normalizeSearchType(?string $type): string
{
    $type = strtolower(trim((string) $type));
    return in_array($type, ['ip', 'ua', 'cookie'], true) ? $type : 'ip';
}

function buildFingerprintSearchNeedle(string $type, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return ['error' => 'Entrez une valeur à rechercher.'];
    }

    if ($type === 'ip') {
        if (!filter_var($query, FILTER_VALIDATE_IP)) {
            return ['error' => 'Adresse IP invalide.'];
        }

        $prefix = getIpPrefix($query);
        return [
            'hash'       => hashFingerprintValue('ip', $query),
            'prefixHash' => $prefix !== '' ? hashFingerprintValue('ip-prefix', $prefix) : null,
            'display'    => $query,
        ];
    }

    if ($type === 'cookie') {
        return [
            'hash'    => hashFingerprintValue('cookie', $query),
            'display' => $query,
        ];
    }

    $rawUa      = normalizeHeaderValue($query, false);
    $normalized = normalizeUserAgent($rawUa);
    return [
        'rawHash' => $rawUa !== '' ? hashFingerprintValue('raw-ua', $rawUa) : null,
        'hash'    => $normalized['normalized'] !== '' ? hashFingerprintValue('ua', $normalized['normalized']) : null,
        'display' => $query,
        'label'   => $normalized['label'],
    ];
}

function getEventSearchMatchType(string $type, array $needle, array $signals): ?string
{
    if ($type === 'cookie') {
        return ($signals['cookieHash'] ?? null) === ($needle['hash'] ?? null) ? 'exact' : null;
    }

    if ($type === 'ip') {
        if (($signals['ipHash'] ?? null) === ($needle['hash'] ?? null)) {
            return 'exact';
        }
        if (($signals['ipPrefixHash'] ?? null) !== null && ($signals['ipPrefixHash'] ?? null) === ($needle['prefixHash'] ?? null)) {
            return 'prefix';
        }
        return null;
    }

    if (($signals['rawUaHash'] ?? null) === ($needle['rawHash'] ?? null)) {
        return 'exact';
    }
    if (($signals['uaHash'] ?? null) === ($needle['hash'] ?? null)) {
        return 'normalized';
    }

    return null;
}

function searchFingerprintRecords(string $type, string $query, int $limit = 60): array
{
    $type   = normalizeSearchType($type);
    $needle = buildFingerprintSearchNeedle($type, $query);

    if (isset($needle['error'])) {
        return [
            'type'   => $type,
            'query'  => $query,
            'error'  => $needle['error'],
            'events' => [],
            'counts' => [],
        ];
    }

    $events   = pruneVoteEvents(loadVoteEvents(), loadSettings()['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS);
    $profiles = loadVoteProfiles();
    $matches  = [];
    $counts   = ['allowed' => 0, 'removed' => 0, 'blocked' => 0];
    $siblings = ['cookies' => [], 'ips' => [], 'uas' => [], 'targets' => []];

    foreach ($events as $event) {
        $signals   = is_array($event['signals'] ?? null) ? $event['signals'] : [];
        $matchType = getEventSearchMatchType($type, $needle, $signals);
        if ($matchType === null) {
            continue;
        }

        $event['searchMatch'] = $matchType;
        $matches[]            = $event;

        $outcome = $event['outcome'] ?? 'allowed';
        if (isset($counts[$outcome])) {
            $counts[$outcome]++;
        }

        if (!empty($signals['cookieHash'])) {
            $siblings['cookies'][$signals['cookieHash']] = $signals['cookieMask'] ?? 'unknown';
        }
        if (!empty($signals['ipHash'])) {
            $siblings['ips'][$signals['ipHash']] = $signals['ipMask'] ?? 'unknown';
        }
        if (!empty($signals['uaHash'])) {
            $siblings['uas'][$signals['uaHash']] = $signals['uaLabel'] ?? 'Unknown';
        }

        $username = (string) ($event['username'] ?? '');
        if ($username !== '') {
            $siblings['targets'][$username] = (int) ($siblings['targets'][$username] ?? 0) + 1;
        }
    }

    usort($matches, static function (array $a, array $b): int {
        return parseIsoTimestamp($b['timestamp'] ?? null) <=> parseIsoTimestamp($a['timestamp'] ?? null);
    });

    $matches = array_slice($matches, 0, $limit);
    arsort($siblings['targets']);

    $profile = null;
    if ($type === 'cookie' && !empty($needle['hash']) && isset($profiles[$needle['hash']])) {
        $profile = $profiles[$needle['hash']];
    }

    return [
        'type'       => $type,
        'query'      => $query,
        'normalized' => $needle['label'] ?? null,
        'events'     => $matches,
        'profile'    => $profile,
        'counts'     => $counts,
        'siblings'   => [
            'cookies' => array_values($siblings['cookies']),
            'ips'     => array_values($siblings['ips']),
            'uas'     => array_values($siblings['uas']),
            'targets' => $siblings['targets'],
        ],
    ];
}

function getRecentFingerprintIncidents(int $limit = 12): array
{
    $events = pruneVoteEvents(loadVoteEvents(), loadSettings()['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS);

    usort($events, static function (array $a, array $b): int {
        return parseIsoTimestamp($b['timestamp'] ?? null) <=> parseIsoTimestamp($a['timestamp'] ?? null);
    });

    $incidents = [];
    foreach ($events as $event) {
        $flags   = array_values(array_filter((array) ($event['flags'] ?? []), 'is_string'));
        $blocked = ($event['outcome'] ?? '') === 'blocked';

        if (!$blocked && $flags === []) {
            continue;
        }

        $incidents[] = $event;
        if (count($incidents) >= $limit) {
            break;
        }
    }

    return $incidents;
}

function summarizeFingerprintMetrics(): array
{
    $events    = pruneVoteEvents(loadVoteEvents(), loadSettings()['retentionDays'] ?? FINGERPRINT_RETENTION_DAYS);
    $profiles  = loadVoteProfiles();
    $blocked24 = 0;
    $cutoff    = time() - 86400;

    foreach ($events as $event) {
        if (($event['outcome'] ?? '') !== 'blocked') {
            continue;
        }
        if (parseIsoTimestamp($event['timestamp'] ?? null) >= $cutoff) {
            $blocked24++;
        }
    }

    uasort($profiles, static function (array $a, array $b): int {
        $scoreA = (int) ($a['stats']['blockedAttempts'] ?? 0) + (int) ($a['stats']['cookieResetMatches'] ?? 0);
        $scoreB = (int) ($b['stats']['blockedAttempts'] ?? 0) + (int) ($b['stats']['cookieResetMatches'] ?? 0);
        return $scoreB <=> $scoreA;
    });

    return [
        'events'         => count($events),
        'blockedLast24h' => $blocked24,
        'profiles'       => count($profiles),
        'topProfiles'    => array_slice(array_values($profiles), 0, 5),
    ];
}

function matchFingerprintAgainstProfile(array $fingerprint, array $profile): array
{
    $sameCookie      = ($profile['cookieHash'] ?? null) === ($fingerprint['cookieHash'] ?? null);
    $sameIp          = !empty($fingerprint['ipHash']) && isset($profile['linkedIps'][$fingerprint['ipHash']]);
    $sameIpPrefix    = !empty($fingerprint['ipPrefixHash']) && isset($profile['linkedPrefixes'][$fingerprint['ipPrefixHash']]);
    $sameUa          = !empty($fingerprint['uaHash']) && isset($profile['linkedUas'][$fingerprint['uaHash']]);
    $sameLanguage    = !empty($fingerprint['acceptLanguageHash']) && isset($profile['linkedLanguages'][$fingerprint['acceptLanguageHash']]);
    $sameClientHints = !empty($fingerprint['clientHintsHash']) && isset($profile['linkedClientHints'][$fingerprint['clientHintsHash']]);
    $sameCountry     = !empty($fingerprint['country']) && isset($profile['linkedCountries'][$fingerprint['country']]);

    $flags = [];
    if (!$sameCookie && $sameIp && $sameUa && $sameLanguage) {
        $flags[] = 'cookie_reset_suspected';
    } elseif ($sameIp && !$sameUa) {
        $flags[] = 'shared_network_guard';
    }

    $level = 'none';
    if ($sameCookie) {
        $level = 'exact_cookie';
    } elseif ($sameIp && $sameUa && $sameLanguage) {
        $level = 'same_ip_ua_language';
    } elseif ($sameIp && $sameUa) {
        $level = 'same_ip_ua';
    } elseif ($sameIpPrefix && $sameUa && $sameLanguage && ($sameClientHints || $sameCountry)) {
        $level = 'same_ip_prefix_ua_language_context';
    } elseif ($sameIpPrefix && $sameUa) {
        $level = 'same_ip_prefix_ua';
    } elseif ($sameIp) {
        $level = 'single_ip';
    } elseif ($sameUa) {
        $level = 'single_ua';
    }

    $matchedSignals = [];
    if ($sameCookie) {
        $matchedSignals[] = 'cookie';
    }
    if ($sameIp) {
        $matchedSignals[] = 'ip';
    }
    if ($sameIpPrefix) {
        $matchedSignals[] = 'ip_prefix';
    }
    if ($sameUa) {
        $matchedSignals[] = 'ua';
    }
    if ($sameLanguage) {
        $matchedSignals[] = 'language';
    }
    if ($sameClientHints) {
        $matchedSignals[] = 'client_hints';
    }
    if ($sameCountry) {
        $matchedSignals[] = 'country';
    }

    return [
        'level'               => $level,
        'matchedSignals'      => $matchedSignals,
        'flags'               => $flags,
        'candidateCookieHash' => $profile['cookieHash'] ?? null,
        'candidateCookieMask' => $profile['cookieMask'] ?? 'unknown',
    ];
}

function getMatchRank(string $level): int
{
    return match ($level) {
        'exact_cookie' => 90,
        'same_ip_ua_language' => 80,
        'same_ip_ua' => 70,
        'same_ip_prefix_ua_language_context' => 60,
        'same_ip_prefix_ua' => 40,
        'single_ip' => 20,
        'single_ua' => 10,
        default => 0,
    };
}

function shouldBlockVoteMatch(string $mode, string $level): bool
{
    $mode = normalizeVoteSecurityMode($mode);

    if ($mode === 'review') {
        return $level === 'exact_cookie';
    }

    if ($mode === 'balanced') {
        return in_array($level, ['exact_cookie', 'same_ip_ua_language'], true);
    }

    return in_array($level, ['exact_cookie', 'same_ip_ua', 'same_ip_ua_language', 'same_ip_prefix_ua_language_context'], true);
}

function findStrongestVoteMatchForEntry(array $fingerprint, array $entry, array $profiles): array
{
    $activeCookies = array_values(array_unique(array_merge(
        array_filter((array) ($entry['votesUp'] ?? []), 'is_string'),
        array_filter((array) ($entry['votesDown'] ?? []), 'is_string')
    )));

    $bestMatch = [
        'level'               => 'none',
        'matchedSignals'      => [],
        'flags'               => [],
        'candidateCookieHash' => null,
        'candidateCookieMask' => null,
    ];

    foreach ($activeCookies as $cookieHash) {
        if ($cookieHash === ($fingerprint['cookieHash'] ?? null)) {
            continue;
        }

        if (!isset($profiles[$cookieHash]) || !is_array($profiles[$cookieHash])) {
            continue;
        }

        $match = matchFingerprintAgainstProfile($fingerprint, $profiles[$cookieHash]);
        if (getMatchRank($match['level']) > getMatchRank($bestMatch['level'])) {
            $bestMatch = $match;
        }
    }

    return $bestMatch;
}

function getFingerprintDriftFlags(array $fingerprint, array $profiles): array
{
    $profile = $profiles[$fingerprint['cookieHash']] ?? null;
    if (!is_array($profile)) {
        return [];
    }

    $flags = [];
    if (!empty($fingerprint['uaHash']) && !isset($profile['linkedUas'][$fingerprint['uaHash']]) && !empty($profile['linkedUas'])) {
        $flags[] = 'header_consistency_shift';
    }

    if (!empty($fingerprint['country']) && !isset($profile['linkedCountries'][$fingerprint['country']]) && !empty($profile['linkedCountries'])) {
        $flags[] = 'country_change_hint';
    }

    return $flags;
}

function buildVoteBlockedMessage(string $mode, string $matchLevel): string
{
    $mode = normalizeVoteSecurityMode($mode);

    return match ($matchLevel) {
        'same_ip_ua_language' => "Vote bloque : cette combinaison IP/navigateur/langue semble deja avoir vote pour cette image ({$mode}).",
        'same_ip_ua' => "Vote bloque : cette combinaison IP et navigateur semble deja avoir vote pour cette image ({$mode}).",
        'same_ip_prefix_ua_language_context' => "Vote bloque : ce reseau et ce navigateur ressemblent fortement a un vote deja enregistre ({$mode}).",
        'exact_cookie' => 'Ce navigateur a deja vote pour cette image.',
        default => 'Vote bloque par la protection anti-doublon.',
    };
}
