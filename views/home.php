<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paws Cards Arena</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header">
    <h1>Paws Cards Arena</h1>
    <?php if ($user): ?>
        <div class="session-box">
            <span>Connecté: <strong><?= htmlspecialchars($user['username']) ?></strong> (<?= htmlspecialchars($user['role']) ?>)</span>
            <button data-action="logout">Logout</button>
        </div>
    <?php endif; ?>
</header>

<main class="container">
    <section class="panel">
        <h2>Connexion / Inscription</h2>
        <div class="auth-grid">
            <form id="loginForm">
                <h3>Login</h3>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Se connecter</button>
            </form>

            <form id="registerForm">
                <h3>Register</h3>
                <input type="text" name="username" placeholder="Username" required>
                <button type="submit">Créer un compte</button>
                <p class="hint">Le mot de passe est affiché une seule fois.</p>
            </form>
        </div>
        <div id="authMessage" class="message"></div>
    </section>

    <?php if ($user): ?>
        <section class="panel">
            <h2>Ma carte quotidienne</h2>
            <button id="claimBtn">Récupérer ma carte du jour</button>
            <div id="claimResult" class="message"></div>
        </section>

        <section class="panel">
            <h2>Recherche de cartes</h2>
            <div class="search-row">
                <input type="number" id="searchNumber" placeholder="Numéro (défaut)">
                <select id="searchRarity">
                    <option value="">Toutes raretés</option>
                    <option>Common</option>
                    <option>Rare</option>
                    <option>Epic</option>
                    <option>Legendary</option>
                </select>
                <button id="searchBtn">Rechercher</button>
            </div>
            <div id="cardsGrid" class="cards-grid"></div>
        </section>

        <section class="panel">
            <h2>Mon deck</h2>
            <div id="deckGrid" class="cards-grid"></div>
        </section>
    <?php endif; ?>

    <?php if ($user && $user['role'] === 'admin'): ?>
        <section class="panel">
            <h2>Dashboard Admin</h2>
            <table id="adminTable">
                <thead>
                <tr>
                    <th>User</th><th>Role</th><th>Cards</th><th>Limit</th><th>Actions</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div id="adminMessage" class="message"></div>
        </section>
    <?php endif; ?>
</main>

<div id="cardModal" class="modal" hidden>
    <div class="modal-content">
        <button class="close-modal">×</button>
        <img id="modalImage" src="" alt="card">
        <h3 id="modalTitle"></h3>
    </div>
</div>

<script>
window.APP_BOOT = {
    user: <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="assets/js/app.js" defer></script>
</body>
</html>
