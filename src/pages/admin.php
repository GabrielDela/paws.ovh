<?php
require_once __DIR__ . '/../../config.php';

$letter = strtoupper($_GET['letter'] ?? 'TOP');
$search = trim($_GET['search'] ?? '');
$token  = $_GET['token'] ?? '';

$images = getImages($letter, $search);
$alphabet = getAlphabet();
$total = count($images);

$awaiting = loadAwaiting();
$awaitingCount = count($awaiting);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paws.ovh — Administration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/styles.css">
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────── -->
<header class="site-header">
    <nav class="nav-inner">
        <a href="/" class="brand">
            <span class="brand-paw">🐾</span>
            <span class="brand-name">Paws<span class="brand-dot">.ovh</span></span>
        </a>
        <form class="token-form" method="GET">
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">
            <input
                class="token-input"
                type="text"
                name="token"
                placeholder="Token d'administration…"
                value="<?= htmlspecialchars($token) ?>"
                autocomplete="off">
            <button class="btn-upload" type="submit">Valider</button>
        </form>
        <a href="/" class="nav-admin">← Galerie</a>
    </nav>
</header>

<!-- ── Admin content ────────────────────────────────────────── -->
<div class="admin-content">

    <!-- Flash messages -->
    <?php if (isset($_GET['uploadSuccess'])): ?>
        <div class="flash flash-success">✓ <?= htmlspecialchars($_GET['uploadSuccess']) ?></div>
    <?php elseif (isset($_GET['uploadError'])): ?>
        <div class="flash flash-error">✗ <?= htmlspecialchars($_GET['uploadError']) ?></div>
    <?php endif; ?>

    <!-- Regenerate thumbnails -->
    <form method="POST" action="/regenerate-thumbnails" onsubmit="return confirm('Régénérer toutes les thumbnails ?');" style="margin-bottom:1rem;">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button type="submit" class="btn-upload" style="background:var(--surface3);color:var(--text-muted);gap:.4rem;display:inline-flex;align-items:center;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>
            Régénérer les thumbnails
        </button>
    </form>

    <!-- Upload -->
    <div class="upload-card">
        <h2>Téléverser des images</h2>
        <form id="upload-form" action="/upload" method="post" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <!-- Drop zone -->
            <div id="drop-zone" class="drop-zone">
                <input type="file" id="file-input" name="files[]" accept="image/*" multiple hidden>
                <div class="drop-zone-inner" id="drop-label">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p>Glissez vos images ici</p>
                    <span>ou <button type="button" class="drop-browse" onclick="document.getElementById('file-input').click()">parcourir</button></span>
                    <small>JPG, PNG, GIF, WebP — max 10 Mo par fichier</small>
                </div>
            </div>

            <!-- Preview grid -->
            <div id="preview-grid" class="preview-grid" hidden></div>

            <!-- Submit -->
            <div id="upload-actions" class="upload-actions" hidden>
                <span id="upload-count" class="upload-file-count"></span>
                <button type="button" class="btn-clear" onclick="clearFiles()">Vider</button>
                <button type="submit" class="btn-upload" id="submit-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Uploader
                </button>
            </div>
        </form>
    </div>

    <!-- Awaiting moderation -->
    <?php if ($awaitingCount > 0): ?>
    <div class="awaiting-section">
        <h2 class="awaiting-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Soumissions en attente
            <span class="awaiting-badge"><?= $awaitingCount ?></span>
        </h2>

        <div class="awaiting-grid">
            <?php foreach ($awaiting as $entry): ?>
            <div class="awaiting-card">
                <div class="awaiting-card-header">
                    <span class="awaiting-pseudo"><?= htmlspecialchars($entry['originalPseudo']) ?></span>
                    <span class="awaiting-date"><?= htmlspecialchars($entry['date'] ?? '') ?></span>
                </div>

                <div class="awaiting-photos">
                    <!-- Main photo (with pseudo) -->
                    <div class="awaiting-photo">
                        <span class="awaiting-photo-label">Avec pseudo</span>
                        <img
                            src="/public/awaiting/thumbnail/<?= htmlspecialchars($entry['id']) ?>_main.png"
                            alt="Photo principale"
                            loading="lazy"
                            class="awaiting-photo-img"
                            onclick="openAwaitingModal('/public/awaiting/<?= htmlspecialchars($entry['mainFile']) ?>')">
                    </div>
                    <!-- Verification photo -->
                    <div class="awaiting-photo">
                        <span class="awaiting-photo-label">Vérification</span>
                        <img
                            src="/public/awaiting/thumbnail/<?= htmlspecialchars($entry['id']) ?>_verify.png"
                            alt="Photo vérification"
                            loading="lazy"
                            class="awaiting-photo-img"
                            onclick="openAwaitingModal('/public/awaiting/<?= htmlspecialchars($entry['verifyFile']) ?>')">
                    </div>
                </div>

                <div class="awaiting-info">
                    <small>IP: <?= htmlspecialchars($entry['ip'] ?? '?') ?></small>
                </div>

                <div class="awaiting-actions">
                    <form method="POST" action="/moderate" style="display:inline;">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="submission_id" value="<?= htmlspecialchars($entry['id']) ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn-approve" onclick="return confirm('Approuver la soumission de « <?= htmlspecialchars(addslashes($entry['originalPseudo'])) ?> » ?');">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                            Approuver
                        </button>
                    </form>
                    <form method="POST" action="/moderate" style="display:inline;">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="submission_id" value="<?= htmlspecialchars($entry['id']) ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn-danger" onclick="return confirm('Rejeter la soumission de « <?= htmlspecialchars(addslashes($entry['originalPseudo'])) ?> » ?');">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            Rejeter
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="awaiting-empty">
        <p>Aucune soumission en attente.</p>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <section class="filter-bar" style="padding:.5rem 0; border:none;">
        <div class="filter-inner">
            <?php foreach ($alphabet as $char): ?>
                <a href="?letter=<?= urlencode($char) ?>&token=<?= urlencode($token) ?>"
                   class="filter-pill <?= $letter === $char && !$search ? 'active' : '' ?>">
                    <?= htmlspecialchars($char) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Search -->
    <div class="gallery-header" style="margin-top:1rem;">
        <span class="gallery-count">
            <?= $total ?> image<?= $total !== 1 ? 's' : '' ?>
            <?php if ($search): ?>
                pour « <strong><?= htmlspecialchars($search) ?></strong> »
            <?php else: ?>
                — <strong><?= htmlspecialchars($letter) ?></strong>
            <?php endif; ?>
        </span>
        <form method="get" style="display:flex;gap:.5rem;">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">
            <input
                class="token-input"
                style="width:200px;"
                type="text"
                name="search"
                placeholder="Rechercher…"
                value="<?= htmlspecialchars($search) ?>">
            <button class="btn-upload" type="submit">OK</button>
            <?php if ($search): ?>
                <a href="?letter=<?= urlencode($letter) ?>&token=<?= urlencode($token) ?>" class="btn-upload" style="background:var(--surface3);color:var(--text-muted);">✕</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Gallery -->
    <?php if ($images): ?>
    <div class="gallery-grid" style="margin-top:1rem;">
        <?php foreach ($images as $img): ?>
        <article class="card">
            <div class="card-img-wrap" style="cursor:default;">
                <img
                    class="card-img"
                    src="<?= htmlspecialchars($img['thumbnail']) ?>"
                    alt="<?= htmlspecialchars($img['username']) ?>"
                    loading="lazy">
            </div>
            <div class="card-body">
                <p class="card-name"><?= htmlspecialchars($img['username']) ?></p>
                <div class="admin-card-stats">
                    <span class="stat-up">▲ <?= (int)$img['votesUp'] ?></span>
                    <span class="stat-down">▼ <?= (int)$img['votesDown'] ?></span>
                    <span class="stat-score">(<?= $img['score'] >= 0 ? '+' : '' ?><?= (int)$img['score'] ?>)</span>
                </div>
                <form
                    method="POST"
                    action="/delete"
                    onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($img['username'])) ?> ?');">
                    <input type="hidden" name="filename" value="<?= htmlspecialchars($img['username']) ?>.<?= htmlspecialchars($img['ext']) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <button class="btn-danger" type="submit">Supprimer</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🐾</div>
        <p>Aucune image trouvée.</p>
    </div>
    <?php endif; ?>

</div>

<footer class="site-footer">
    <span>© <?= date('Y') ?> Paws.ovh — Administration</span>
</footer>

<!-- ── Awaiting Modal ──────────────────────────────────────── -->
<div id="awaiting-modal" class="modal" role="dialog" aria-modal="true" aria-label="Image soumission">
    <div class="modal-backdrop" onclick="closeAwaitingModal()"></div>
    <div class="modal-box">
        <button class="modal-close" onclick="closeAwaitingModal()" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <img id="awaiting-modal-img" src="" alt="" class="modal-img">
    </div>
</div>

<script>
function openAwaitingModal(src) {
    const modal = document.getElementById('awaiting-modal');
    const img = document.getElementById('awaiting-modal-img');
    img.src = src;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeAwaitingModal() {
    const modal = document.getElementById('awaiting-modal');
    modal.classList.remove('open');
    document.getElementById('awaiting-modal-img').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAwaitingModal(); });

// ── Drag & drop multi-upload ───────────────────────────────────
const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('file-input');
const previewGrid= document.getElementById('preview-grid');
const actions    = document.getElementById('upload-actions');
const countLabel = document.getElementById('upload-count');

let selectedFiles = [];

// Drag events
['dragenter','dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
});
['dragleave','drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('drag-over'); });
});
dropZone.addEventListener('drop', e => addFiles(e.dataTransfer.files));
fileInput.addEventListener('change', () => addFiles(fileInput.files));

// Click anywhere on drop zone (except browse button) to open picker
dropZone.addEventListener('click', e => {
    if (e.target.classList.contains('drop-browse') || e.target === fileInput) return;
    fileInput.click();
});

function addFiles(fileList) {
    const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    Array.from(fileList).forEach(f => {
        if (!allowed.includes(f.type)) return;
        if (selectedFiles.find(x => x.name === f.name && x.size === f.size)) return; // dedupe
        selectedFiles.push(f);
    });
    syncUI();
}

function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    syncUI();
}

function clearFiles() {
    selectedFiles = [];
    fileInput.value = '';
    syncUI();
}

function syncUI() {
    // Rebuild preview grid
    previewGrid.innerHTML = '';
    selectedFiles.forEach((f, i) => {
        const item = document.createElement('div');
        item.className = 'preview-item';

        const img = document.createElement('img');
        img.className = 'preview-thumb';
        img.alt = f.name;
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(f);

        const name = document.createElement('span');
        name.className = 'preview-name';
        name.textContent = f.name.replace(/\.[^.]+$/, ''); // strip ext

        const del = document.createElement('button');
        del.className = 'preview-remove';
        del.type = 'button';
        del.innerHTML = '&times;';
        del.title = 'Retirer';
        del.onclick = () => removeFile(i);

        item.append(img, name, del);
        previewGrid.appendChild(item);
    });

    const hasFiles = selectedFiles.length > 0;
    previewGrid.hidden = !hasFiles;
    actions.hidden     = !hasFiles;
    countLabel.textContent = selectedFiles.length + ' fichier' + (selectedFiles.length > 1 ? 's' : '') + ' sélectionné' + (selectedFiles.length > 1 ? 's' : '');

    // Sync FileInput with our selectedFiles array via DataTransfer
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
}

// Loading state on submit
document.getElementById('upload-form').addEventListener('submit', () => {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Upload en cours…';
});
</script>

</body>
</html>
