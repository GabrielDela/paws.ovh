# Plan : Système de soumission publique avec validation admin

## Résumé
Ajouter un onglet "Soumettre" accessible à tous (sans token admin) permettant de déposer 2 images + un pseudo. Les soumissions vont dans un dossier "awaiting" (max 50). L'admin peut valider ou rejeter depuis son dashboard.

## Fichiers à créer/modifier

### 1. Créer `public/awaiting/` et `public/awaiting/thumbnail/`
- Dossier pour les images en attente
- Sous-dossier pour les thumbnails des images en attente

### 2. Créer `awaiting.json` (stockage des soumissions)
Structure :
```json
[
  {
    "id": "unique_id",
    "pseudo": "MonPseudo",
    "image_display": "awaiting/monpseudo_display.png",
    "image_proof": "awaiting/monpseudo_proof.png",
    "submitted_at": "2026-02-25T12:00:00",
    "ip": "hash_ip"
  }
]
```

### 3. Modifier `config.php`
- Ajouter constante `AWAITING_DIR`, `AWAITING_JSON`, `MAX_AWAITING` (50)
- Ajouter fonctions `loadAwaiting()`, `saveAwaiting()`, `countAwaiting()`

### 4. Créer `submit.php` (endpoint POST pour soumission publique)
- Valide : pseudo (alphanum, 3-20 chars), 2 fichiers image
- Vérifie que le max 50 n'est pas atteint
- Sauvegarde les 2 images dans `public/awaiting/` avec noms : `{id}_display.{ext}` et `{id}_proof.{ext}`
- Génère thumbnails pour les deux
- Ajoute l'entrée dans `awaiting.json`
- Rate limiting basique par IP (max 3 soumissions par IP)

### 5. Créer `validate.php` (endpoint POST admin pour valider/rejeter)
- Requiert token admin
- Action "approve" : déplace l'image display vers `public/{pseudo}.{ext}`, génère thumbnail, supprime les 2 images awaiting
- Action "reject" : supprime les 2 images awaiting + thumbnails
- Met à jour `awaiting.json`

### 6. Créer `src/pages/submit.php` (page de soumission publique)
- Formulaire avec :
  - Champ pseudo (texte)
  - Zone upload image 1 : "Photo avec votre pseudo affiché"
  - Zone upload image 2 : "Photo de vérification (seul, fond uni, sans pseudo)"
  - Bouton soumettre
- Design cohérent avec le reste du site (dark theme, même CSS)
- Compteur de places restantes (X/50)

### 7. Modifier `src/pages/admin.php`
- Ajouter section "Soumissions en attente" en haut du dashboard
- Pour chaque soumission : afficher les 2 images côte à côte, le pseudo, la date
- Boutons "Valider ✓" et "Rejeter ✗" pour chaque soumission

### 8. Modifier `index.php` (routeur)
- Ajouter route `/submit` → `src/pages/submit.php`
- Ajouter route POST `/submit` → `submit.php`
- Ajouter route POST `/validate` → `validate.php`

### 9. Modifier `src/pages/index.php` (navigation)
- Ajouter lien "Soumettre" dans le header (visible pour tous)

### 10. Modifier `public/styles.css`
- Styles pour la page de soumission (formulaire, zones upload)
- Styles pour la section awaiting dans l'admin (grille de review, boutons valider/rejeter)

## Flux utilisateur

### Soumission (public) :
1. L'utilisateur clique sur "Soumettre" dans le header
2. Il remplit son pseudo + uploade 2 images
3. Si < 50 en attente → sauvegarde dans awaiting/
4. Message de confirmation "Votre soumission est en attente de validation"

### Validation (admin) :
1. L'admin voit les soumissions en attente en haut de son dashboard
2. Il voit les 2 images côte à côte pour chaque soumission
3. "Valider" → l'image display est déplacée dans la galerie principale
4. "Rejeter" → les images sont supprimées

## Sécurité
- Rate limiting par IP (max 3 soumissions en attente par IP)
- Validation MIME type + extension des fichiers
- Sanitisation du pseudo
- Max 50 soumissions en attente
- Seul l'admin peut valider/rejeter
