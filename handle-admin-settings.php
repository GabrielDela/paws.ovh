<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token = $_POST['token'] ?? '';
if (!isAdminTokenValid($token)) {
    http_response_code(403);
    exit('Invalid token');
}

$mode = normalizeVoteSecurityMode($_POST['vote_security_mode'] ?? 'review');

saveSettings([
    'voteSecurityMode' => $mode,
]);

header('Location: /admin?token=' . urlencode($token) . '&fingerprintSuccess=' . urlencode('Mode de securite mis a jour : ' . $mode));
exit;
