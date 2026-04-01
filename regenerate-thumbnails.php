<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$token = $_POST['token'] ?? '';
if ($token !== ADMIN_TOKEN) { http_response_code(403); exit('Invalid token'); }

$uploadDir = UPLOAD_FOLDER;
$thumbDir  = UPLOAD_FOLDER . '/thumbnail';

if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$exts      = allowedExtensions();
$regenerated = 0;
$errors      = 0;

foreach (scandir($uploadDir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $exts)) continue;

    $base      = pathinfo($file, PATHINFO_FILENAME);
    $source    = "{$uploadDir}/{$file}";
    $thumbPath = "{$thumbDir}/{$base}.png";

    // Remove old thumbnail(s) for this base name
    foreach (glob("{$thumbDir}/{$base}.*") ?: [] as $old) { @unlink($old); }

    if (createThumbnail($source, $thumbPath, 300, 300)) {
        $regenerated++;
    } else {
        error_log("regenerate-thumbnails: failed for {$source}");
        $errors++;
    }
}

$msg = "{$regenerated} thumbnail" . ($regenerated > 1 ? 's régénérées' : ' régénérée');
if ($errors) $msg .= " — {$errors} erreur" . ($errors > 1 ? 's' : '');

header('Location: /admin?uploadSuccess=' . urlencode($msg) . '&token=' . urlencode($token));
exit;
