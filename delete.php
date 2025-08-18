<?php
// delete.php

require_once 'config.php';

// Afficher les erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Vérifier si la méthode HTTP est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée');
}

// Vérifier si les paramètres requis sont envoyés dans le formulaire
if (!isset($_POST['filename']) || !isset($_POST['token']) || !isset($_POST['csrf_token'])) {
    http_response_code(400);
    exit('Paramètres manquants');
}

$filename = basename($_POST['filename']);
$token = $_POST['token'];
$csrfToken = $_POST['csrf_token'];

if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Token CSRF invalide');
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(400);
    exit('Extension non autorisée');
}

// Vérifier que le token est valide
if (!hash_equals(ADMIN_TOKEN, $token)) {
    http_response_code(403);
    exit('Token invalide');
}

// Vérifier que le fichier existe dans le dossier public
$filePath = UPLOAD_FOLDER . '/' . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Fichier non trouvé');
}

// Supprimer le fichier
if (unlink($filePath)) {
    // Rediriger vers la page admin avec un message de succès
    header('Location: /admin?uploadSuccess=Image+supprimée+avec+succès&token=' . urlencode($token));
    exit;
} else {
    http_response_code(500);
    exit('Erreur lors de la suppression du fichier');
}
