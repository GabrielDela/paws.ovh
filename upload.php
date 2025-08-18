<?php
// upload.php

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    exit('Aucun fichier reçu.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Token CSRF invalide');
}

$token = $_POST['token'] ?? '';
if (!hash_equals(ADMIN_TOKEN, $token)) {
    http_response_code(403);
    exit('Token invalide');
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    exit('Erreur lors de l\'upload du fichier');
}

if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    exit('Le fichier est trop volumineux');
}

// Validate MIME type
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedMimeTypes[$mimeType])) {
    http_response_code(400);
    exit('Format de fichier non autorisé');
}

$fileExtension = $allowedMimeTypes[$mimeType];

// Validate dimensions
$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    http_response_code(400);
    exit('Fichier non image');
}

list($width, $height) = $imageInfo;
if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
    http_response_code(400);
    exit('Dimensions de l\'image trop grandes');
}

// Define paths using sanitized original filename
$uploadDir = __DIR__ . '/public';
$thumbDir = __DIR__ . '/public/thumbnail';

$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$sanitizedName = preg_replace('/[^A-Za-z0-9_-]/', '_', $originalName);
$filename = "{$sanitizedName}.{$fileExtension}";
$uploadPath = $uploadDir . '/' . $filename; // overwrite if file already exists

$thumbBase = pathinfo($filename, PATHINFO_FILENAME);
$thumbPath = $thumbDir . '/' . $thumbBase . '.png'; // thumbnail always PNG

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    exit('Erreur lors du déplacement du fichier');
}

// Create thumbnail (always saved as .png)
if (!createThumbnail($uploadPath, $thumbPath, 300, 300)) {
    http_response_code(500);
    exit('Erreur lors de la création de la miniature');
}

header('Location: /admin?uploadSuccess=Image+uploadée+avec+succès&token=' . urlencode($token));
exit;

// --------- Thumbnail creation function ----------
function createThumbnail($src, $dest, $maxWidth, $maxHeight) {
    $info = getimagesize($src);
    if (!$info) return false;

    list($width, $height) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($src); break;
        case 'image/png':  $image = imagecreatefrompng($src);  break;
        case 'image/gif':  $image = imagecreatefromgif($src);  break;
        case 'image/webp': $image = imagecreatefromwebp($src); break;
        default: return false;
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG/WEBP
    if (in_array($mime, ['image/png', 'image/webp'])) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagepng($thumb, $dest); // always save thumbnail as PNG

    imagedestroy($image);
    imagedestroy($thumb);
    return true;
}

