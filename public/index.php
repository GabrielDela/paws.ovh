<?php
/**
 * Point d'entree principal - Affiche la page d'accueil.
 */
require_once __DIR__ . '/../app/bootstrap.php';
initSession();

// Charger la vue principale
require_once ROOT_PATH . '/views/home.php';
