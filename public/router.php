<?php
/**
 * Router API - Dispatche les requetes vers l'ApiController.
 */
require_once __DIR__ . '/../app/bootstrap.php';
initSession();

$endpoint = $_GET['endpoint'] ?? '';

if (empty($endpoint)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Endpoint manquant.']);
    exit;
}

$controller = createApiController();
$controller->dispatch($endpoint);
