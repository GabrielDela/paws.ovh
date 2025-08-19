<?php
// src/pages/admin/index.php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../services/news/images.php';

$letter = strtoupper($_GET['letter'] ?? 'TOP');
$search = trim($_GET['search'] ?? '');
$token = $_GET['token'] ?? '';
$csrfToken = getCsrfToken();
$images = getImages($letter, $search);
$alphabet = getAlphabet();
$totalImages = count($images);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Paws.ovh - Administration</title>
    <meta name="description" content="Panneau d'administration pour gérer les images">
    <link rel="stylesheet" href="/public/styles.css">
</head>

<body>
    <nav>
        <span class="page-title"><a class="title-ref" href="https://paws.ovh">Paws.ovh</a></span>
        <form class="token-form" method="GET">
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">
            <input id="token" class="token-input" type="text" name="token" placeholder="Token" value="<?= htmlspecialchars($token) ?>">
        </form>
    </nav>
    <section class="page-content">
        <div class="messagebox">
            <div id="message">
                <?php
                if (isset($_GET['uploadSuccess'])) {
                    echo '<div style="padding: 20px; background-color: #c9ecf9;">' . htmlspecialchars($_GET['uploadSuccess']) . '</div>';
                } elseif (isset($_GET['uploadError'])) {
                    echo '<div style="padding: 20px; background-color: #f9c9c9;">' . htmlspecialchars($_GET['uploadError']) . '</div>';
                }
                ?>
            </div>
        </div>
        <h2 class="content-title">Section de téléversement</h2>
        <form action="/api/news/upload" method="post" enctype="multipart/form-data">
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">
            <input type="file" name="file" required><br><br>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit">Téléverser</button>
        </form>
        <h2 class="content-title">Gestion de la galerie</h2>
        <span>
            <?= $totalImages ?> images pour
            <?php if ($search): ?>
                la recherche "<?= htmlspecialchars($search) ?>"
            <?php else: ?>
                la lettre <?= htmlspecialchars($letter) ?>
            <?php endif; ?>
        </span>
        <br><br>
        <form method="get" class="search-form">
            <input type="text" name="search" placeholder="Recherche par nom..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit">Rechercher</button>
        </form>
        <br>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/admin?letter=<?= urlencode($char) ?>&token=<?= urlencode($token) ?>"><?= htmlspecialchars($char) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="content-gallery">
            <?php foreach ($images as $img): ?>
                <div class="gallery-container">
                    <img class="gallery-image"
                        src="/public/thumbnail/<?= htmlspecialchars($img['username']) ?>.png"
                        alt="<?= htmlspecialchars($img['username']) ?>"
                        loading="lazy">
                    <p><?= htmlspecialchars($img['username']) ?></p>
                    <p>
                        Votes Up: <?= (int)$img['votesUp'] ?> |
                        Votes Down: <?= (int)$img['votesDown'] ?>
                    </p>
                    <form method="POST" action="/api/news/delete" onsubmit="return confirm('Supprimer <?= htmlspecialchars($img['username']) ?>.png ?');">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($img['username']) ?>.png">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn-upload">Supprimer</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/admin?letter=<?= urlencode($char) ?>&token=<?= urlencode($token) ?>"><?= htmlspecialchars($char) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <footer>
        <span class="copyright">© Paws.ovh</span>
    </footer>
</body>

</html>