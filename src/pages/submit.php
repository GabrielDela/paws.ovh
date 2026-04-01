<?php
require_once __DIR__ . '/../../config.php';

$awaiting = loadAwaiting();
$awaitingCount = count($awaiting);
$isFull = $awaitingCount >= MAX_AWAITING;

// Check if this visitor already has a pending submission
$submitterToken = getSubmitterToken();
$alreadyPending = false;
foreach ($awaiting as $entry) {
    if (($entry['submitterToken'] ?? '') === $submitterToken) {
        $alreadyPending = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paws.ovh — Soumettre une image</title>
    <meta name="description" content="Soumettez votre image pour la galerie Paws.ovh">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/styles.css">
</head>
<body>

<!-- ── Navigation ─────────────────────────────────────────────── -->
<header class="site-header">
    <nav class="nav-inner">
        <a href="/" class="brand">
            <span class="brand-paw">🐾</span>
            <span class="brand-name">Paws<span class="brand-dot">.ovh</span></span>
        </a>
        <div class="nav-links">
            <a href="/" class="nav-link">Galerie</a>
            <a href="/submit" class="nav-link active">Soumettre</a>
        </div>
    </nav>
</header>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<section class="hero">
    <h1 class="hero-title">Soumettre une image</h1>
    <p class="hero-sub">Proposez votre photo pour la galerie — un admin la validera.</p>
</section>

<main class="main-content">

    <!-- Flash messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="flash flash-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="flash flash-error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <?php if ($isFull): ?>
        <div class="submit-blocked">
            <div class="empty-icon">🐾</div>
            <h2>File d'attente pleine</h2>
            <p>Il y a actuellement <?= $awaitingCount ?> soumissions en attente de validation (maximum <?= MAX_AWAITING ?>). Réessayez plus tard !</p>
            <a href="/" class="btn-primary">Retour à la galerie</a>
        </div>
    <?php elseif ($alreadyPending): ?>
        <div class="submit-blocked">
            <div class="empty-icon">⏳</div>
            <h2>Soumission en attente</h2>
            <p>Vous avez déjà une soumission en cours de validation. Patientez qu'elle soit traitée avant d'en envoyer une nouvelle.</p>
            <a href="/" class="btn-primary">Retour à la galerie</a>
        </div>
    <?php else: ?>

    <div class="submit-card">
        <form id="submit-form" action="/submit" method="post" enctype="multipart/form-data">

            <!-- Pseudo -->
            <div class="form-group">
                <label class="form-label" for="pseudo">Votre pseudo</label>
                <input type="text" id="pseudo" name="pseudo" class="form-input" required
                       minlength="2" maxlength="30"
                       placeholder="Ex: MonPseudo"
                       pattern="[a-zA-Z0-9\-_]+"
                       title="Lettres, chiffres, tirets et underscores uniquement">
                <small class="form-hint">Lettres, chiffres, tirets et underscores — 2 à 30 caractères</small>
            </div>

            <!-- Photo 1: Main (with pseudo) -->
            <div class="form-group">
                <label class="form-label">Photo 1 — Avec votre pseudo affiché</label>
                <p class="form-desc">Cette photo doit montrer votre pseudo visible à l'écran.</p>
                <div class="submit-drop-zone" id="drop-main" data-target="photo_main">
                    <input type="file" id="photo_main" name="photo_main" accept="image/*" required hidden>
                    <div class="drop-zone-inner" id="drop-main-label">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p>Glissez votre image ici</p>
                        <span>ou <button type="button" class="drop-browse" onclick="document.getElementById('photo_main').click()">parcourir</button></span>
                        <small>JPG, PNG, GIF, WebP — max 10 Mo</small>
                    </div>
                    <div class="submit-preview" id="preview-main" hidden>
                        <img id="preview-main-img" alt="Aperçu photo principale">
                        <button type="button" class="preview-remove-btn" onclick="clearFile('main')">&times;</button>
                    </div>
                </div>
            </div>

            <!-- Photo 2: Verification (no pseudo, solo, solid background) -->
            <div class="form-group">
                <label class="form-label">Photo 2 — Photo de vérification</label>
                <p class="form-desc">Conditions requises :</p>
                <ul class="form-rules">
                    <li>Pas de pseudo visible</li>
                    <li>Seul sur l'image</li>
                    <li>Fond gris ou unicolore</li>
                </ul>
                <div class="submit-drop-zone" id="drop-verify" data-target="photo_verify">
                    <input type="file" id="photo_verify" name="photo_verify" accept="image/*" required hidden>
                    <div class="drop-zone-inner" id="drop-verify-label">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <p>Glissez votre image ici</p>
                        <span>ou <button type="button" class="drop-browse" onclick="document.getElementById('photo_verify').click()">parcourir</button></span>
                        <small>JPG, PNG, GIF, WebP — max 10 Mo</small>
                    </div>
                    <div class="submit-preview" id="preview-verify" hidden>
                        <img id="preview-verify-img" alt="Aperçu photo vérification">
                        <button type="button" class="preview-remove-btn" onclick="clearFile('verify')">&times;</button>
                    </div>
                </div>
            </div>

            <!-- Queue info -->
            <div class="submit-queue-info">
                <span><?= $awaitingCount ?> / <?= MAX_AWAITING ?> places occupées dans la file d'attente</span>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-primary btn-submit" id="submit-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Envoyer ma soumission
            </button>
        </form>
    </div>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <span>&copy; <?= date('Y') ?> Paws.ovh — Fait avec 🐾</span>
</footer>

<!-- ── Toast ──────────────────────────────────────────────────── -->
<div id="toast" class="toast" aria-live="polite"></div>

<script>
// ── Drop zones for single files ──────────────────────────────
['main', 'verify'].forEach(key => {
    const zone  = document.getElementById(`drop-${key}`);
    const input = document.getElementById(key === 'main' ? 'photo_main' : 'photo_verify');
    const label = document.getElementById(`drop-${key}-label`);
    const prev  = document.getElementById(`preview-${key}`);
    const img   = document.getElementById(`preview-${key}-img`);

    ['dragenter','dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('drag-over'); }));

    zone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            setFile(key, file);
        }
    });

    zone.addEventListener('click', e => {
        if (e.target.classList.contains('drop-browse') || e.target === input || e.target.classList.contains('preview-remove-btn')) return;
        input.click();
    });

    input.addEventListener('change', () => {
        if (input.files[0]) setFile(key, input.files[0]);
    });
});

function setFile(key, file) {
    const input = document.getElementById(key === 'main' ? 'photo_main' : 'photo_verify');
    const label = document.getElementById(`drop-${key}-label`);
    const prev  = document.getElementById(`preview-${key}`);
    const img   = document.getElementById(`preview-${key}-img`);

    // Set file on input via DataTransfer
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;

    // Show preview
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; };
    reader.readAsDataURL(file);

    label.hidden = true;
    prev.hidden = false;
}

function clearFile(key) {
    const input = document.getElementById(key === 'main' ? 'photo_main' : 'photo_verify');
    const label = document.getElementById(`drop-${key}-label`);
    const prev  = document.getElementById(`preview-${key}`);
    const img   = document.getElementById(`preview-${key}-img`);

    input.value = '';
    img.src = '';
    label.hidden = false;
    prev.hidden = true;
}

// Loading state on submit
document.getElementById('submit-form')?.addEventListener('submit', () => {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';
});
</script>

</body>
</html>
