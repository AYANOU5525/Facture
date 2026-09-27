# FactuPro — Gestion Commerciale & Plateforme B2B

**FactuPro** est une application web de gestion commerciale complète dédiée aux PME. Elle combine un outil de gestion des ventes au comptoir et des factures avec une plateforme d'interconnexion **B2B Connect** (commandes inter-entreprises, annonces, messagerie intégrée et synchronisation automatique des stocks).

---

## Problématique & Solution

### Le Constat

Les PME utilisent souvent plusieurs outils fragmentés pour :

- Gérer leurs produits, ventes au comptoir et factures.
- Trouver de nouveaux partenaires commerciaux ou déstocker des marchandises.
- Passer des commandes inter-entreprises sans perdre la traçabilité des prix et des stocks.
- Échanger sur l'avancement des commandes.

### La Solution FactuPro

Une plateforme unique qui centralise le cycle commercial interne et ouvre un canal d'échanges inter-entreprises sécurisé et traçable.

---

## Fonctionnalités principales

### Gestion Commerciale & Ventes

- **Authentification & Droits** : chiffrement des mots de passe avec `bcrypt`, gestion des rôles (`Admin` / `Utilisateur`), confirmation d'email obligatoire à l'inscription (code à 6 chiffres) et verrouillage progressif du formulaire de connexion après 3 échecs (30s, puis 60s, 120s...).
- **Catalogue & Stocks** : enregistrement des produits, alertes de rupture et mise à jour dynamique des réserves.
- **Ventes Directes & Facturation** : saisie des encaissements au comptoir, choix des modes de paiement et impression des factures.
- **Tableau de Bord** : statistiques de ventes et suivi des indicateurs clés d'activité.

### Module B2B Connect & Messagerie

- **Annuaire d'Entreprises** : profils certifiés avec NIF, secteur et score de fiabilité calculé sur l'historique des transactions.
- **Espace Déstockage & Annonces** : offres promotionnelles et opportunités d'affaires d'autres PME.
- **Commandes Inter-Entreprises** : flux complet de commande B2B :
  `en_attente → validee → expediee → livree`.
- **Messagerie Directe** : chat B2B par commande via polling AJAX, permettant de négocier et d'échanger en temps réel.
- **Flux de Stock Automatisé** : décrémentation et incrémentation automatiques des stocks partenaires à la validation d'une commande B2B.

---

## Installation de la base de données

Le schéma complet tient en deux fichiers, **à exécuter dans cet ordre** sur une base neuve :

1. `database/facturation.sql` — schéma de base + données de démonstration.
2. `database/migrations.sql` — **fichier unique** regroupant toutes les migrations correctives (dans l'ordre où elles ont été écrites : correction MCD `Ligne_Vente`/`Id_Vendeur`/triggers `Montant_Total`, index `Notification_B2B`, index codes-barres, adresse de livraison B2B, `Client`/`Ligne_Produit`/`Contenir`, confirmation d'email). **Indispensable** : sans cette étape, la connexion échoue avec `SQLSTATE[42S22]: Unknown column 'Email_Verifie'` et toute vente échoue avec `Unknown column 'Id_Vendeur'`/`'Id_Client'`.

   Toute nouvelle migration est ajoutée à la suite de ce même fichier (voir le sommaire en tête de `database/migrations.sql`) — ne pas créer de nouveau fichier `migration_xxx.sql` séparé.

```bash
mysql -u root --default-character-set=utf8mb4 facturation < database/facturation.sql
mysql -u root --default-character-set=utf8mb4 facturation < database/migrations.sql
```

**Environnement Docker** : les mêmes fichiers doivent être rejoués séparément contre la base du conteneur (`docker-compose.yml` expose MySQL sur le port hôte `3307`, distinct des `3306` habituels) — les deux bases ne se synchronisent jamais automatiquement entre elles. **Attention** : `database/facturation.sql` contient des `DROP TABLE IF EXISTS` et réinitialise les données de démonstration — ne jamais le rejouer sur une base contenant des données à conserver ; sur une base déjà installée, ne rejouer que `database/migrations.sql`.

`--default-character-set=utf8mb4` évite que le client `mysql` retombe sur un jeu de caractères par défaut (ex. `cp850` sous Windows) et corrompe les caractères accentués des commentaires de colonnes lors de l'import.

Pour réinitialiser les données de démonstration ensuite (comptes de test à mot de passe connu) : `php database/reset_demo.php`.

## Envoi d'emails (confirmation d'inscription, mot de passe oublié)

En local, `.env` pointe par défaut vers **Mailpit**, un attrape-mails de dev lancé par `docker-compose.yml` (service `mailpit`) : aucun email n'est réellement envoyé sur Internet, ils sont consultables sur **http://localhost:8025**. Fonctionne aussi bien depuis Laragon natif (`127.0.0.1:1025`, port publié par le conteneur) que depuis l'app Docker (`mailpit:1025`, nom de service sur le réseau interne — surchargé automatiquement dans `docker-compose.yml`).

Pour un vrai envoi (ex. démonstration avec réception sur un vrai téléphone) : remplacer `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` dans `.env` par un compte SMTP réel (voir les commentaires dans `.env.example`, ex. Gmail + mot de passe d'application).

## HTTPS (nécessaire pour le scanner mobile en réseau local)

Le navigateur d'un téléphone bloque la caméra (`getUserMedia`) sur une connexion HTTP non sécurisée dès que l'adresse n'est pas littéralement `localhost` — ce qui est toujours le cas quand le téléphone rejoint le PC par son IP réseau locale. **Sous Docker**, un certificat auto-signé est généré automatiquement au build de l'image et servi sur `https://<IP-du-PC>:8443` (voir `Dockerfile`, `docker/apache-ssl.conf`). Ouvrir FactuPro sur le PC via `https://<IP-du-PC>:8443` avant de générer le QR de scan mobile ; accepter l'avertissement de certificat non reconnu sur le téléphone (normal pour un certificat auto-signé, à faire une seule fois).

**Sous Laragon natif**, activer HTTPS manuellement : menu Laragon → clic droit → Apache → SSL (ou "Auto HTTPS") ; Laragon génère alors son propre certificat local.

Sans HTTPS, l'appli reste pleinement utilisable — seule la caméra du téléphone est indisponible : un champ de saisie manuelle du code-barres reste toujours proposé sur `scanner_mobile.php` (cf. `views/scanner_mobile/index.php`) et le PC affiche un avertissement explicite au moment de générer le QR (cf. `ScanSessionController::createSession()`).

## Sauvegarde de la base

`php database/backup.php` (ou `docker exec facturation_app php database/backup.php` pour le conteneur) génère un dump horodaté dans `database/backups/` (non versionné). Lit les identifiants depuis `.env` — valable pour l'environnement où la commande est lancée.
