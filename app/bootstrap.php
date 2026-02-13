<?php
/**
 * Bootstrap - Autoloading et injection de dependances.
 */

// Chemin racine du projet
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');

// Autoloader simple PSR-4-like
spl_autoload_register(function (string $className) {
    $paths = [
        ROOT_PATH . '/app/DAL/' . $className . '.php',
        ROOT_PATH . '/app/BLL/' . $className . '.php',
        ROOT_PATH . '/app/API/' . $className . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Configuration de la session securisee
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 3600,
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Fabrique les services avec injection de dependances.
 */
function createApiController(): ApiController
{
    $userRepo = new UserRepository();
    $cardRepo = new CardRepository();
    $deckRepo = new DeckRepository();

    $authService = new AuthService($userRepo, $deckRepo);
    $cardService = new CardService($cardRepo, $deckRepo, $userRepo);

    return new ApiController($authService, $cardService);
}
