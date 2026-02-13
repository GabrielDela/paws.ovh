<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWS - Collection de Cartes</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <h1 class="logo">PAWS<span class="logo-dot">.</span></h1>
            <nav id="nav-bar">
                <!-- Rempli dynamiquement par JS -->
            </nav>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="main-content">

        <!-- Section Auth (Login / Register) -->
        <section id="section-auth" class="section hidden">
            <div class="auth-container">
                <div class="auth-tabs">
                    <button class="auth-tab active" data-tab="login">Connexion</button>
                    <button class="auth-tab" data-tab="register">Inscription</button>
                </div>

                <!-- Login Form -->
                <form id="form-login" class="auth-form">
                    <div class="form-group">
                        <label for="login-username">Nom d'utilisateur</label>
                        <input type="text" id="login-username" required autocomplete="username" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label for="login-password">Mot de passe</label>
                        <input type="password" id="login-password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                    <div id="login-message" class="form-message"></div>
                </form>

                <!-- Register Form -->
                <form id="form-register" class="auth-form hidden">
                    <div class="form-group">
                        <label for="register-username">Nom d'utilisateur</label>
                        <input type="text" id="register-username" required autocomplete="username" maxlength="20"
                               pattern="[a-zA-Z0-9_]+" title="Lettres, chiffres et underscores uniquement">
                    </div>
                    <button type="submit" class="btn btn-primary">Creer un compte</button>
                    <div id="register-message" class="form-message"></div>
                </form>
            </div>
        </section>

        <!-- Section Collection (Catalogue) -->
        <section id="section-collection" class="section hidden">
            <h2 class="section-title">Catalogue des Cartes</h2>

            <!-- Barre de recherche -->
            <div class="search-bar">
                <input type="text" id="search-input" placeholder="Rechercher une carte...">
                <select id="search-type">
                    <option value="number">Par numero</option>
                    <option value="rarity">Par rarete</option>
                    <option value="name">Par nom</option>
                </select>
                <button id="btn-search" class="btn btn-secondary">Rechercher</button>
            </div>

            <!-- Filtre rapide par rarete -->
            <div class="rarity-filters">
                <button class="rarity-btn" data-rarity="">Toutes</button>
                <button class="rarity-btn rarity-common" data-rarity="Common">Common</button>
                <button class="rarity-btn rarity-rare" data-rarity="Rare">Rare</button>
                <button class="rarity-btn rarity-epic" data-rarity="Epic">Epic</button>
                <button class="rarity-btn rarity-legendary" data-rarity="Legendary">Legendary</button>
            </div>

            <div id="cards-grid" class="cards-grid">
                <!-- Cartes chargees dynamiquement -->
            </div>
        </section>

        <!-- Section Mon Deck -->
        <section id="section-deck" class="section hidden">
            <h2 class="section-title">Mon Deck</h2>

            <!-- Bouton claim journalier -->
            <div class="claim-section">
                <button id="btn-claim" class="btn btn-claim">Recuperer ma carte du jour</button>
                <p id="claim-status" class="claim-status"></p>
            </div>

            <div id="deck-grid" class="cards-grid">
                <!-- Deck charge dynamiquement -->
            </div>
            <p id="deck-empty" class="empty-message hidden">Votre deck est vide. Recuperez votre premiere carte !</p>
        </section>

        <!-- Section Admin -->
        <section id="section-admin" class="section hidden">
            <h2 class="section-title">Administration</h2>

            <div id="admin-users" class="admin-panel">
                <!-- Panel admin charge dynamiquement -->
            </div>
        </section>

    </main>

    <!-- Modal zoom carte -->
    <div id="card-modal" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <div id="modal-card" class="modal-card">
                <!-- Contenu de la carte en zoom -->
            </div>
        </div>
    </div>

    <!-- Modal carte obtenue -->
    <div id="claim-modal" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content claim-reveal">
            <h3>Nouvelle carte obtenue !</h3>
            <div id="claim-card-display" class="card-reveal">
                <!-- Carte revelee -->
            </div>
            <button class="btn btn-primary" id="btn-close-claim">Super !</button>
        </div>
    </div>

    <!-- Notification toast -->
    <div id="toast-container" class="toast-container"></div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
