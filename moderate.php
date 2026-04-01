<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$token = $_POST['token'] ?? '';
if ($token !== ADMIN_TOKEN) { http_response_code(403); exit('Invalid token'); }

$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$submissionId = $_POST['submission_id'] ?? '';

if (!in_array($action, ['approve', 'reject']) || $submissionId === '') {
    http_response_code(400);
    exit('Invalid request');
}

$awaiting = loadAwaiting();
$found = null;
$foundIdx = null;

foreach ($awaiting as $i => $entry) {
    if ($entry['id'] === $submissionId) {
        $found = $entry;
        $foundIdx = $i;
        break;
    }
}

if ($found === null) {
    header('Location: /admin?token=' . urlencode($token) . '&uploadError=' . urlencode('Soumission introuvable.'));
    exit;
}

$awaitDir = AWAITING_FOLDER;
$awaitThumbDir = AWAITING_FOLDER . '/thumbnail';

if ($action === 'approve') {
    // Move main photo to public gallery
    $srcMain = "{$awaitDir}/{$found['mainFile']}";
    $destMain = UPLOAD_FOLDER . "/{$found['pseudo']}.{$found['mainExt']}";

    // Remove any existing file with same pseudo
    foreach (glob(UPLOAD_FOLDER . "/{$found['pseudo']}.*") ?: [] as $old) { @unlink($old); }
    foreach (glob(UPLOAD_FOLDER . "/thumbnail/{$found['pseudo']}.*") ?: [] as $old) { @unlink($old); }

    if (file_exists($srcMain)) {
        rename($srcMain, $destMain);
        // Generate proper thumbnail
        $thumbDest = UPLOAD_FOLDER . "/thumbnail/{$found['pseudo']}.png";
        createThumbnail($destMain, $thumbDest, 300, 300);
    }

    // Clean up verification photo and awaiting thumbnails
    @unlink("{$awaitDir}/{$found['verifyFile']}");
    @unlink("{$awaitThumbDir}/{$found['id']}_main.png");
    @unlink("{$awaitThumbDir}/{$found['id']}_verify.png");

    // Remove from awaiting list
    unset($awaiting[$foundIdx]);
    saveAwaiting($awaiting);

    $msg = "Soumission de « {$found['originalPseudo']} » approuvée et ajoutée à la galerie.";
    header('Location: /admin?token=' . urlencode($token) . '&uploadSuccess=' . urlencode($msg));
    exit;
}

if ($action === 'reject') {
    // Delete all files
    @unlink("{$awaitDir}/{$found['mainFile']}");
    @unlink("{$awaitDir}/{$found['verifyFile']}");
    @unlink("{$awaitThumbDir}/{$found['id']}_main.png");
    @unlink("{$awaitThumbDir}/{$found['id']}_verify.png");

    // Remove from awaiting list
    unset($awaiting[$foundIdx]);
    saveAwaiting($awaiting);

    $msg = "Soumission de « {$found['originalPseudo']} » rejetée.";
    header('Location: /admin?token=' . urlencode($token) . '&uploadSuccess=' . urlencode($msg));
    exit;
}
