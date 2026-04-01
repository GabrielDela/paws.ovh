<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

// Rate limit: one pending submission per IP
$submitterToken = getSubmitterToken();
$awaiting = loadAwaiting();

// Check global limit
if (count($awaiting) >= MAX_AWAITING) {
    header('Location: /submit?error=' . urlencode('La file d\'attente est pleine (maximum ' . MAX_AWAITING . ' soumissions). Réessayez plus tard.'));
    exit;
}

// Check if this IP already has a pending submission
foreach ($awaiting as $entry) {
    if (($entry['submitterToken'] ?? '') === $submitterToken) {
        header('Location: /submit?error=' . urlencode('Vous avez déjà une soumission en attente. Patientez qu\'elle soit traitée.'));
        exit;
    }
}

// Validate pseudo
$pseudo = trim($_POST['pseudo'] ?? '');
if ($pseudo === '' || mb_strlen($pseudo) < 2 || mb_strlen($pseudo) > 30) {
    header('Location: /submit?error=' . urlencode('Le pseudo doit contenir entre 2 et 30 caractères.'));
    exit;
}

$safePseudo = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $pseudo);

// Check pseudo not already taken (in main gallery or awaiting)
$existingImages = getImages('*');
foreach ($existingImages as $img) {
    if (strcasecmp($img['username'], $safePseudo) === 0) {
        header('Location: /submit?error=' . urlencode('Ce pseudo est déjà utilisé dans la galerie.'));
        exit;
    }
}
foreach ($awaiting as $entry) {
    if (strcasecmp($entry['pseudo'] ?? '', $safePseudo) === 0) {
        header('Location: /submit?error=' . urlencode('Ce pseudo est déjà en attente de validation.'));
        exit;
    }
}

// Validate files: photo_main (with pseudo) and photo_verify (verification photo)
if (empty($_FILES['photo_main']['name']) || empty($_FILES['photo_verify']['name'])) {
    header('Location: /submit?error=' . urlencode('Les deux photos sont requises.'));
    exit;
}

$exts = allowedExtensions();
$errors = [];

foreach (['photo_main', 'photo_verify'] as $field) {
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "{$field}: erreur d'upload ({$file['error']})";
        continue;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "{$field}: fichier trop volumineux (max 10 Mo)";
        continue;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts)) {
        $errors[] = "{$field}: format non supporté (JPG, PNG, GIF, WebP uniquement)";
    }
}

if ($errors) {
    header('Location: /submit?error=' . urlencode(implode(' | ', $errors)));
    exit;
}

// Create awaiting directories
$awaitDir = AWAITING_FOLDER;
$awaitThumbDir = AWAITING_FOLDER . '/thumbnail';
foreach ([$awaitDir, $awaitThumbDir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Generate unique submission ID
$submissionId = uniqid('sub_', true);

// Save main photo
$extMain = strtolower(pathinfo($_FILES['photo_main']['name'], PATHINFO_EXTENSION));
$mainFilename = "{$submissionId}_main.{$extMain}";
$mainPath = "{$awaitDir}/{$mainFilename}";
if (!move_uploaded_file($_FILES['photo_main']['tmp_name'], $mainPath)) {
    header('Location: /submit?error=' . urlencode('Impossible de sauvegarder la photo principale.'));
    exit;
}

// Generate thumbnail for main photo
$thumbPath = "{$awaitThumbDir}/{$submissionId}_main.png";
createThumbnail($mainPath, $thumbPath, 300, 300);

// Save verification photo
$extVerify = strtolower(pathinfo($_FILES['photo_verify']['name'], PATHINFO_EXTENSION));
$verifyFilename = "{$submissionId}_verify.{$extVerify}";
$verifyPath = "{$awaitDir}/{$verifyFilename}";
if (!move_uploaded_file($_FILES['photo_verify']['tmp_name'], $verifyPath)) {
    @unlink($mainPath);
    @unlink($thumbPath);
    header('Location: /submit?error=' . urlencode('Impossible de sauvegarder la photo de vérification.'));
    exit;
}

// Generate thumbnail for verification photo
$verifyThumbPath = "{$awaitThumbDir}/{$submissionId}_verify.png";
createThumbnail($verifyPath, $verifyThumbPath, 300, 300);

// Add to awaiting list
$awaiting[] = [
    'id'             => $submissionId,
    'pseudo'         => $safePseudo,
    'originalPseudo' => $pseudo,
    'mainFile'       => $mainFilename,
    'mainExt'        => $extMain,
    'verifyFile'     => $verifyFilename,
    'verifyExt'      => $extVerify,
    'submitterToken' => $submitterToken,
    'ip'             => getClientIp(),
    'date'           => date('Y-m-d H:i:s'),
];
saveAwaiting($awaiting);

header('Location: /submit?success=' . urlencode('Votre soumission a été envoyée ! Elle sera examinée par un administrateur.'));
exit;
