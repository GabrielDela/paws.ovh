<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$token    = $_POST['token']    ?? '';
$filename = $_POST['filename'] ?? '';

if ($token !== ADMIN_TOKEN) { http_response_code(403); exit('Invalid token'); }

// Sanitize: only allow safe filenames, no path traversal
$filename = basename($filename);
if (!preg_match('/^[a-zA-Z0-9\-_]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
    http_response_code(400); exit('Invalid filename');
}

$base     = pathinfo($filename, PATHINFO_FILENAME);
$filePath = UPLOAD_FOLDER . '/' . $filename;
$thumbPath= UPLOAD_FOLDER . '/thumbnail/' . $base . '.png';

if (!file_exists($filePath)) { http_response_code(404); exit('File not found'); }

@unlink($filePath);
@unlink($thumbPath);

header('Location: /admin?uploadSuccess=' . urlencode("Image « {$base} » supprimée") . '&token=' . urlencode($token));
exit;
