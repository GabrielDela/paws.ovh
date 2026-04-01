<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

// Detect when PHP silently dropped the request because post_max_size was exceeded.
// In that case $_POST and $_FILES are both empty despite a non-zero Content-Length.
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    $limitBytes = return_bytes_cfg(ini_get('post_max_size'));
    $limitMb    = round($limitBytes / 1024 / 1024);
    http_response_code(413);
    exit("Requête trop volumineuse : la taille totale dépasse la limite serveur ({$limitMb} Mo). Réduisez le nombre ou la taille des fichiers.");
}

// Detect max_file_uploads exceeded — PHP silently drops files beyond the limit
// but keeps $_POST intact, so we can detect it via comparing counts.
if (!empty($_FILES['files'])) {
    $received = count(array_filter($_FILES['files']['name'], fn($n) => $n !== ''));
    $limit    = (int) ini_get('max_file_uploads');
    if ($received >= $limit) {
        http_response_code(400);
        exit("Trop de fichiers : la limite serveur est de {$limit} fichiers par envoi.");
    }
}

$token = $_POST['token'] ?? '';
if ($token !== ADMIN_TOKEN) { http_response_code(403); exit('Invalid token'); }

// Support both single file (file) and multiple files (files[])
$rawFiles = [];
if (!empty($_FILES['files']['name'][0])) {
    // Multiple: restructure the PHP $_FILES mess into a clean array
    foreach ($_FILES['files']['name'] as $i => $name) {
        $rawFiles[] = [
            'name'     => $name,
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error'    => $_FILES['files']['error'][$i],
            'size'     => $_FILES['files']['size'][$i],
        ];
    }
} elseif (!empty($_FILES['file']['name'])) {
    $rawFiles[] = $_FILES['file'];
} else {
    http_response_code(400); exit('No file received');
}

$uploadDir = UPLOAD_FOLDER;
$thumbDir  = UPLOAD_FOLDER . '/thumbnail';

// Ensure directories exist and are writable
foreach ([$uploadDir, $thumbDir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!is_writable($dir)) { http_response_code(500); exit("Directory not writable: {$dir}"); }
}

$success = [];
$errors  = [];

foreach ($rawFiles as $file) {
    $name = $file['name'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "{$name}: erreur d'upload ({$file['error']})";
        continue;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "{$name}: fichier trop volumineux";
        continue;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, allowedExtensions())) {
        $errors[] = "{$name}: format non supporté";
        continue;
    }

    $base       = preg_replace('/[^a-zA-Z0-9\-_]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $uploadPath = "{$uploadDir}/{$base}.{$ext}";
    $thumbPath  = "{$thumbDir}/{$base}.png";

    // Remove previous files with same base name
    foreach (glob("{$uploadDir}/{$base}.*") ?: [] as $old) { @unlink($old); }
    foreach (glob("{$thumbDir}/{$base}.*")  ?: [] as $old) { @unlink($old); }

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $errors[] = "{$name}: impossible de sauvegarder le fichier";
        continue;
    }

    if (!createThumbnail($uploadPath, $thumbPath, 300, 300)) {
        error_log("createThumbnail failed for {$uploadPath} → {$thumbPath}");
    }

    $success[] = $base;
}

// Build redirect message
$parts = [];
if ($success) $parts[] = count($success) . ' image' . (count($success) > 1 ? 's uploadées' : ' uploadée') . ' : ' . implode(', ', $success);
if ($errors)  $parts[] = implode(' | ', $errors);

$param = $errors && !$success ? 'uploadError' : 'uploadSuccess';
$msg   = implode(' — ', $parts);

header('Location: /admin?' . $param . '=' . urlencode($msg) . '&token=' . urlencode($token));
exit;
