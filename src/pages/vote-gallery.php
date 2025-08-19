<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/news/images.php';
$csrfToken = getCsrfToken();
$image = getNextUnvotedImage();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Vote Gallery</title>
    <link rel="stylesheet" href="/public/styles.css">
    <style>
        body{
            overflow: hidden;
        }

        img {
            max-height: 60vh;
            margin-bottom: 1em;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }

        #vote-container{
            height: 80vh;
            background-color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10vh;
        }

        button {
            font-size: 1.2em;
            padding: 0.6em 1.5em;
            margin: 1em;
        }

    </style>
</head>

<body>
    <nav>
        <span class="page-title"><a class="title-ref" href="https://paws.ovh">Paws.ovh</a></span>
    </nav>

    <div id="vote-container">
        <?php if ($image): ?>
            <img id="vote-image" src="/public/<?= htmlspecialchars($image['username']) ?>.png" alt="<?= htmlspecialchars($image['username']) ?>">
            <div class="votes-full">
                <button class="up voted" onclick="submitVote('up')">👍 Up</button>
                <button class="down voted" onclick="submitVote('down')">👎 Down</button>
            </div>
            <input type="hidden" id="current-username" value="<?= htmlspecialchars($image['username']) ?>">
        <?php else: ?>
            <h1>🎉 Vous avez voté pour toutes les images !</h1>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = '<?= $csrfToken ?>';
        function submitVote(direction) {
            const username = document.getElementById('current-username').value;

            fetch('/api/news/vote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username,
                        direction,
                        csrf_token: csrfToken
                    })
                })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + (json.error || "Impossible d'enregistrer le vote."));
                    }
                })
                .catch(err => {
                    alert('Erreur réseau');
                    console.error(err);
                });
        }
    </script>

</body>

</html>