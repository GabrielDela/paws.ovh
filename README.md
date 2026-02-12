# Paws Cards Arena (PHP Vanilla)

Application web PHP 8+ sans framework avec architecture en couches : API / BLL / DAL.

## Arborescence

```text
/app
  /API
  /BLL
  /DAL
/public
  index.php
  router.php
  /assets
/data
  users.json
  cards.json
  decks.json
/views
```

## Fonctionnalités

- Register via username uniquement, mot de passe généré automatiquement et affiché une seule fois.
- Login sécurisé (password_hash/password_verify + session).
- Réclamation quotidienne d'une carte.
- Recherche dynamique (numéro et rareté) via fetch.
- Deck utilisateur stocké en JSON.
- Dashboard admin protégé :
  - Voir utilisateurs + cartes.
  - Changer daily limit.
  - Régénérer password.
  - Reset claim journalier.
  - Ajouter / supprimer carte.

## Identifiants admin initiaux

- Username: `admin`
- Password: `Admin123!`

## Lancer le projet

```bash
php -S 127.0.0.1:8080 -t public
```

Puis ouvrir: <http://127.0.0.1:8080/index.php>

## Notes techniques

- Stockage exclusif JSON dans `/data`.
- Router API: `public/router.php?endpoint=...`
- CSS/JS modernes en vanilla.
