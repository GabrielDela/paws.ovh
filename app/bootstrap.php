<?php

declare(strict_types=1);

use App\API\ApiController;
use App\BLL\AuthService;
use App\BLL\CardService;
use App\DAL\CardRepository;
use App\DAL\DeckRepository;
use App\DAL\JsonStorage;
use App\DAL\UserRepository;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

$storage = new JsonStorage(dirname(__DIR__) . '/data');
$userRepository = new UserRepository($storage);
$deckRepository = new DeckRepository($storage);
$cardRepository = new CardRepository($storage);

$authService = new AuthService($userRepository, $deckRepository);
$cardService = new CardService($cardRepository, $userRepository, $deckRepository);

$apiController = new ApiController($authService, $cardService);
