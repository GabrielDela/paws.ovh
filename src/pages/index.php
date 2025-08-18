<?php
// src/pages/index.php

require_once __DIR__ . '/../../config.php';

$letter = strtoupper($_GET['letter'] ?? 'TOP');
$search = trim($_GET['search'] ?? '');
$images = getImages($letter, $search);
$alphabet = getAlphabet();
$totalImages = count($images);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Paws.ovh - Galerie</title>
    <meta name="description" content="Découvrez les images adorables partagées par notre communauté !">
    <link rel="stylesheet" href="/public/styles.css">
</head>

<body>

    <nav>
         <span class="page-title"><a class="title-ref" href="https://paws.ovh">Paws.ovh</a></span>
    </nav>
    <section class="page-content">
        <h2 class="content-title">Galerie</h2>
        <span>
            <?= $totalImages ?> images pour
            <?php if ($search): ?>
                la recherche "<?= htmlspecialchars($search) ?>"
            <?php else: ?>
                la lettre <?= htmlspecialchars($letter) ?>
            <?php endif; ?>
        </span>

        <br><br>

        Voter toutes les images de la gallerie <a target="_blank" href="/src/pages/vote-gallery.php">ici</a> !

        <br><br>
        <form method="get" class="search-form">
            <input type="text" name="search" placeholder="Recherche par nom..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit">Rechercher</button>
        </form>
        <br>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/src/pages/index.php?letter=<?= urlencode($char) ?>"><?= htmlspecialchars($char) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="content-gallery">
            <div id="imageModal" class="modal" onclick="closeModal(event)">
                <span class="modal-close" onclick="closeModal(event)">&times;</span>
                <img class="modal-content" id="modalImage" alt="">
            </div>
            <?php foreach ($images as $img): ?>
                <div class="gallery-container">
                    <img class="gallery-image"
                        src="/public/thumbnail/<?= htmlspecialchars($img['username']) ?>.png"
                        alt="<?= htmlspecialchars($img['username']) ?>"
                        loading="lazy"
                        onclick="openModal('<?= htmlspecialchars($img['username']) ?>')">
                    <p><?= htmlspecialchars($img['username']) ?></p>
                    <div class="votes">
                        <button onclick="sendVote('<?= $img['username'] ?>', 'up')"
                            class="up <?= $img['userVoted'] === 'up' ? 'voted' : '' ?>">
                            ↑ <?= $img['votesUp'] ?>
                        </button>
                        <button onclick="sendVote('<?= $img['username'] ?>', 'down')"
                            class="down <?= $img['userVoted'] === 'down' ? 'voted' : '' ?>">
                            ↓ <?= $img['votesDown'] ?>
                        </button>
                        <?= $img['userVoted'] === null ? 'Non votée' : null ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pagination">
            <?php foreach ($alphabet as $char): ?>
                <a href="/src/pages/index.php?letter=<?= urlencode($char) ?>"><?= htmlspecialchars($char) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <footer>
        <span class="copyright">© Paws.ovh</span>
    </footer>

    <script>
        function openModal(username) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = `/public/${username}.png`; // adjust if original extension is not fixed
            modalImg.alt = username;
        }

        function closeModal(event) {
            const modal = document.getElementById('imageModal');
            if (event.target.id === 'imageModal' || event.target.classList.contains('modal-close')) {
                modal.style.display = "none";
                document.getElementById('modalImage').src = '';
            }
        }

        function sendVote(username, direction) {
            fetch('/vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username,
                        direction
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // ✅ Reload the page on successful vote
                    } else {
                        alert("Erreur: " + data.error);
                    }
                })
                .catch(err => {
                    console.error("Vote error:", err);
                    alert("Une erreur est survenue.");
                });
        }
    </script>

</body>

</html>