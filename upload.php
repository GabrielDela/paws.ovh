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

$token = $_POST['token'] ?? '';
if ($token !== ADMIN_TOKEN) {
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

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(400);
    exit('Format de fichier non autoris�');
}

// Get the base name (without extension), clean it
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
$cleanName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $baseName);

// Define paths
$uploadDir = __DIR__ . '/public';
$thumbDir = __DIR__ . '/public/thumbnail';
$filename = "{$cleanName}.{$fileExtension}";
$uploadPath = $uploadDir . '/' . $filename;
$thumbPath = $thumbDir . '/' . $cleanName . '.png'; // thumbnail always PNG

// Delete old files with same base name
foreach (glob("$uploadDir/{$cleanName}.*") as $oldFile) {
    unlink($oldFile);
}
foreach (glob("$thumbDir/{$cleanName}.*") as $oldThumb) {
    unlink($oldThumb);
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    exit('Erreur lors du d�placement du fichier');
}

// Create thumbnail (always saved as .png)
if (!createThumbnail($uploadPath, $thumbPath, 300, 300)) {
    http_response_code(500);
    exit('Erreur lors de la cr�ation de la miniature');
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
