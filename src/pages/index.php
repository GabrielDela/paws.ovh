<?php
require_once __DIR__ . '/../../config.php';

$letter = strtoupper($_GET['letter'] ?? 'TOP');
$search = trim($_GET['search'] ?? '');
$images = getImages($letter, $search);
$alphabet = getAlphabet();
$total = count($images);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paws.ovh — Galerie</title>
    <meta name="description" content="Découvrez et votez pour vos images préférées de la communauté Paws.ovh">
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
        <form class="nav-search" method="get" action="/">
            <input type="text" name="search" placeholder="Rechercher…" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            <button type="submit" aria-label="Rechercher">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>
        <a href="/submit" class="nav-submit-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Soumettre
        </a>
    </nav>
</header>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<section class="hero">
    <h1 class="hero-title">La galerie de la communauté</h1>
    <p class="hero-sub">Votez pour vos images préférées — upvote ou downvote !</p>
</section>

<!-- ── Alphabet pills ─────────────────────────────────────────── -->
<section class="filter-bar">
    <div class="filter-inner">
        <?php foreach ($alphabet as $char): ?>
            <a href="?letter=<?= urlencode($char) ?>"
               class="filter-pill <?= $letter === $char && !$search ? 'active' : '' ?>">
                <?= htmlspecialchars($char) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── Gallery ────────────────────────────────────────────────── -->
<main class="main-content">
    <div class="gallery-header">
        <span class="gallery-count">
            <?= $total ?> image<?= $total !== 1 ? 's' : '' ?>
            <?php if ($search): ?>
                pour « <strong><?= htmlspecialchars($search) ?></strong> »
            <?php elseif ($letter !== '*'): ?>
                — <strong><?= htmlspecialchars($letter) ?></strong>
            <?php endif; ?>
        </span>
        <?php if ($search): ?>
            <a href="/" class="reset-search">Effacer la recherche</a>
        <?php endif; ?>
    </div>

    <?php if ($images): ?>
    <div class="gallery-grid" id="gallery">
        <?php foreach ($images as $img): ?>
        <article class="card" data-username="<?= htmlspecialchars($img['username']) ?>">
            <!-- Thumbnail -->
            <div class="card-img-wrap" onclick="openModal('<?= htmlspecialchars($img['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($img['ext'], ENT_QUOTES) ?>')">
                <img
                    src="<?= htmlspecialchars($img['thumbnail']) ?>"
                    alt="<?= htmlspecialchars($img['username']) ?>"
                    loading="lazy"
                    class="card-img">
                <div class="card-overlay">
                    <svg class="overlay-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                </div>
            </div>

            <!-- Info -->
            <div class="card-body">
                <p class="card-name"><?= htmlspecialchars($img['username']) ?></p>

                <!-- Vote row -->
                <div class="vote-row">
                    <button
                        class="vote-btn vote-up <?= $img['userVote'] === 'up' ? 'active' : '' ?>"
                        onclick="castVote('<?= htmlspecialchars($img['username'], ENT_QUOTES) ?>', 'up', this)"
                        aria-label="Upvote">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l8 8H4z"/></svg>
                        <span class="vote-count up-count"><?= (int)$img['votesUp'] ?></span>
                    </button>

                    <span class="vote-score <?= $img['score'] > 0 ? 'positive' : ($img['score'] < 0 ? 'negative' : '') ?>">
                        <?= $img['score'] > 0 ? '+' : '' ?><?= (int)$img['score'] ?>
                    </span>

                    <button
                        class="vote-btn vote-down <?= $img['userVote'] === 'down' ? 'active' : '' ?>"
                        onclick="castVote('<?= htmlspecialchars($img['username'], ENT_QUOTES) ?>', 'down', this)"
                        aria-label="Downvote">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20l-8-8h16z"/></svg>
                        <span class="vote-count down-count"><?= (int)$img['votesDown'] ?></span>
                    </button>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🐾</div>
        <p>Aucune image trouvée.</p>
        <?php if ($search || ($letter !== '*' && $letter !== 'TOP')): ?>
            <a href="/" class="btn-primary">Voir toute la galerie</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- ── Footer ─────────────────────────────────────────────────── -->
<footer class="site-footer">
    <span>© <?= date('Y') ?> Paws.ovh — Fait avec 🐾</span>
</footer>

<!-- ── Modal ──────────────────────────────────────────────────── -->
<div id="modal" class="modal" role="dialog" aria-modal="true" aria-label="Image agrandie">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <img id="modal-img" src="" alt="" class="modal-img">
        <p id="modal-name" class="modal-name"></p>
    </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────── -->
<div id="toast" class="toast" aria-live="polite"></div>

<script>
// ── Modal ──────────────────────────────────────────────────────
function openModal(username, ext) {
    const modal = document.getElementById('modal');
    const img   = document.getElementById('modal-img');
    const name  = document.getElementById('modal-name');
    img.src  = `/public/${encodeURIComponent(username)}.${ext}`;
    img.alt  = username;
    name.textContent = username;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('modal');
    modal.classList.remove('open');
    document.getElementById('modal-img').src = '';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast toast-${type} show`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
}

// ── Vote ───────────────────────────────────────────────────────
function castVote(username, direction, triggerBtn) {
    const card     = triggerBtn.closest('.card');
    const upBtn    = card.querySelector('.vote-up');
    const downBtn  = card.querySelector('.vote-down');
    const scoreEl  = card.querySelector('.vote-score');
    const upCount  = upBtn.querySelector('.up-count');
    const downCount= downBtn.querySelector('.down-count');

    // Optimistic UI lock
    upBtn.disabled   = true;
    downBtn.disabled = true;

    fetch('/vote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, direction })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Erreur inconnue');

        // Update counts
        upCount.textContent   = data.votesUp;
        downCount.textContent = data.votesDown;

        const score = data.score;
        scoreEl.textContent = (score > 0 ? '+' : '') + score;
        scoreEl.className   = 'vote-score' + (score > 0 ? ' positive' : score < 0 ? ' negative' : '');

        // Update active state
        upBtn.classList.toggle('active',   data.userVote === 'up');
        downBtn.classList.toggle('active', data.userVote === 'down');

        const msg = data.userVote === 'up'   ? '👍 Upvoté !'
                  : data.userVote === 'down' ? '👎 Downvoté !'
                  : '↩ Vote retiré';
        showToast(msg, data.userVote === 'up' ? 'success' : data.userVote === 'down' ? 'warning' : 'info');
    })
    .catch(err => {
        showToast('Erreur : ' + err.message, 'error');
    })
    .finally(() => {
        upBtn.disabled   = false;
        downBtn.disabled = false;
    });
}
</script>

</body>
</html>
