<?php
// src/pages/admin.php

require_once __DIR__ . '/../../config.php';

$letter = strtoupper($_GET['letter'] ?? 'TOP');
$search = trim($_GET['search'] ?? '');
$token = $_GET['token'] ?? '';
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
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input id="token" class="token-input" type="text" name="token" placeholder="Token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </form>
    </nav>
    <section class="page-content">
        <div class="messagebox">
            <div id="message">
                <?php
                if (isset($_GET['uploadSuccess'])) {
                    echo '<div style="padding: 20px; background-color: #c9ecf9;">' . htmlspecialchars($_GET['uploadSuccess'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                } elseif (isset($_GET['uploadError'])) {
                    echo '<div style="padding: 20px; background-color: #f9c9c9;">' . htmlspecialchars($_GET['uploadError'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                }
                ?>
            </div>
        </div>
        <h2 class="content-title">Section de téléversement</h2>
        <form action="/upload.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="letter" value="<?= htmlspecialchars($letter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="file" name="file" required><br><br>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button type="submit">Téléverser</button>
        </form>
        <h2 class="content-title">Gestion de la galerie</h2>
        <span>
            <?= $totalImages ?> images pour
            <?php if ($search): ?>
                la recherche "<?= htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            <?php else: ?>
                la lettre <?= htmlspecialchars($letter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            <?php endif; ?>
        </span>
        <br><br>
        <form method="get" class="search-form">
            <input type="text" name="search" placeholder="Recherche par nom..." value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button type="submit">Rechercher</button>
        </form>
        <br>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/src/pages/admin.php?letter=<?= urlencode($char) ?>&token=<?= urlencode($token) ?>"><?= htmlspecialchars($char, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
        <div class="content-gallery">
            <?php foreach ($images as $img): ?>
                <div class="gallery-container">
                    <img class="gallery-image"
                        src="/public/thumbnail/<?= htmlspecialchars($img['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.png"
                        alt="<?= htmlspecialchars($img['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        loading="lazy">
                    <p><?= htmlspecialchars($img['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <p>
                        Votes Up: <?= (int)$img['votesUp'] ?> |
                        Votes Down: <?= (int)$img['votesDown'] ?>
                    </p>
                    <form method="POST" action="/delete.php" onsubmit="return confirm('Supprimer <?= htmlspecialchars($img['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.png ?');">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($img['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.png">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <button type="submit" class="btn-upload">Supprimer</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/src/pages/admin.php?letter=<?= urlencode($char) ?>&token=<?= urlencode($token) ?>"><?= htmlspecialchars($char, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <footer>
        <span class="copyright">© Paws.ovh</span>
    </footer>
</body>

</html>