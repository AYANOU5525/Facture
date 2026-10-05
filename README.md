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

- **Base neuve** : `database/facturation.sql` contient le schéma complet et à jour (21 tables, index, clés étrangères, triggers) ; la base démarre vide.
- **Base déjà installée** : `database/migrations.sql` la met à niveau **sans perte de données** (fichier unique, rejouable sans risque : chaque bloc vérifie s'il a déjà été appliqué). Toute nouvelle mise à jour s'ajoute à la suite de ce fichier, avec une entrée dans son sommaire, et le changement de schéma est aussi reporté dans `facturation.sql`.

```bash
# installation neuve
mysql -u root --default-character-set=utf8mb4 < database/facturation.sql
# mise à jour d'une base existante
mysql -u root --default-character-set=utf8mb4 facturation < database/migrations.sql
```

**Environnement Docker** : MySQL du conteneur est exposé sur le port hôte `3307`, distinct du MySQL Laragon habituel (`3306`). Le `.env` local configure l'application Laragon vers le MySQL Docker (`DB_HOST=127.0.0.1`, `DB_PORT=3307`, identifiants `DOCKER_DB_*`) ; l'application Docker se connecte au même serveur via `db:3306`. Le MySQL local de Laragon peut contenir une copie de test, mais cette copie n'est pas synchronisée automatiquement et n'est pas utilisée par l'application. **Attention** : `database/facturation.sql` contient des `DROP TABLE IF EXISTS` et supprime les tables/données existantes avant de recréer le schéma vide — ne jamais le rejouer sur une base contenant des données à conserver ; sur une base déjà installée, n'utiliser que `database/migrations.sql`.

`--default-character-set=utf8mb4` évite que le client `mysql` retombe sur un jeu de caractères par défaut (ex. `cp850` sous Windows) et corrompe les caractères accentués des commentaires de colonnes lors de l'import.

Le schéma seul ne crée aucun compte de démonstration; le premier compte se crée depuis la page d'inscription.

Pour ajouter un jeu de données fictives complet dans l'environnement de test : `php database/seed_test_data.php`. Le script ajoute cinq entreprises aux données existantes, localisées à Lome (Maritime), Kpalime (Plateaux), Sokode (Centrale), Kara (Kara) et Dapaong (Savanes), avec des montants de test en FCFA, comptes, produits, clients, ventes/factures, annonces et commandes B2B. Il refuse de s'exécuter si ses comptes de test existent déjà. Les comptes ajoutés utilisent le mot de passe commun `FactuTest2026!` et des adresses email réservées en `.test` ; ne jamais exécuter ce script en production.

## Règles de gestion notables

- **TVA** : les prix saisis sont TTC ; la facture affiche HT, TVA et TTC au taux de 18 % (`src/Application/Billing/Vat.php`, seul endroit à modifier si le taux change).
- **Numérotation** : factures et commandes sont numérotées séquentiellement par jour (`FAC-AAAAMMJJ-0001`, `FAC-B2B-…`, `CMD-…`).
- **Annulation de facture** : possible pour une vente au comptoir tant que la marchandise n'est ni expédiée ni livrée ; la facture reste archivée 10 ans (statut « annulée », définitif), le stock est réintégré et la vente sort du chiffre d'affaires. Les factures B2B suivent le cycle de la commande.
- **Commandes urgentes** : sans réponse du vendeur dans le délai demandé, la commande est refusée automatiquement (constaté au prochain chargement des pages B2B ou du tableau de bord) et les deux entreprises sont notifiées.
- **Stock du vendeur confidentiel (B2B)** : l'acheteur ne voit pas le stock de ses fournisseurs et peut commander la quantité qu'il veut. Si le vendeur n'a pas tout, il propose les quantités qu'il peut fournir (bouton « Partiel ») ; le stock proposé est réservé et la commande passe « À confirmer ». L'acheteur choisit : **Accepter** (commande validée sur ces quantités), **Accepter + compléter plus tard** (le manque devient une nouvelle commande « reliquat », reliée à l'originale, que le vendeur validera quand son stock le permettra) ou **Annuler** (stock rendu au vendeur, sans effet sur son score). L'acheteur peut aussi annuler une commande tant qu'elle est en attente.
- **Notifications en temps réel** : compteur sur la cloche et dans le menu, liste déroulante, alerte à l'écran avec signal sonore et compteur dans le titre de l'onglet dès qu'une notification arrive (vérification toutes les 15 s) ; alertes du système d'exploitation en option (HTTPS). Un clic ouvre directement la commande concernée.
- **Menu latéral rétractable** : bouton en haut à gauche pour le réduire en colonne d'icônes ou le rouvrir (choix mémorisé).
- **Factures B2B côté acheteur** : la facture émise par le fournisseur est consultable et imprimable par l'acheteur depuis la commande (bouton « Facture ») et dans Factures › Factures d'achat (HT, TVA, TTC, statut de paiement tenu par le fournisseur).
- **Score de fiabilité B2B** : (commandes livrées + 5) / (livrées + refusées + 5) ; un refus ou un délai dépassé le fait baisser.
- **Confirmation d'email** : 5 codes erronés désactivent le code en cours (un nouveau code peut être demandé après 60 s).

## Tests automatisés

```bash
vendor/bin/phpunit
```

Les tests d'intégration n'utilisent **jamais** la base de dev : `tests/bootstrap.php` recrée à chaque lancement une base isolée `<DB_NAME>_test` (ou `DB_TEST_NAME` dans `.env`) avec le schéma exact de la base de dev, lu en lecture seule, et chaque test crée ses propres données (`tests/Fixtures.php`).

## Envoi d'emails (confirmation d'inscription, mot de passe oublié)

En local, `.env` pointe par défaut vers **Mailpit**, un attrape-mails de dev lancé par `docker-compose.yml` (service `mailpit`) : aucun email n'est réellement envoyé sur Internet, ils sont consultables sur **http://localhost:8025**. Fonctionne aussi bien depuis Laragon natif (`127.0.0.1:1025`, port publié par le conteneur) que depuis l'app Docker (`mailpit:1025`, nom de service sur le réseau interne — surchargé automatiquement dans `docker-compose.yml`).

Pour un vrai envoi (ex. démonstration avec réception sur un vrai téléphone) : remplacer `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` dans `.env` par un compte SMTP réel (voir les commentaires dans `.env.example`, ex. Gmail + mot de passe d'application).

## HTTPS (nécessaire pour le scanner mobile en réseau local)

Le navigateur d'un téléphone bloque la caméra (`getUserMedia`) sur une connexion HTTP non sécurisée dès que l'adresse n'est pas littéralement `localhost` — ce qui est toujours le cas quand le téléphone rejoint le PC par son IP réseau locale. **Sous Docker**, un certificat auto-signé est généré automatiquement au build de l'image et servi sur `https://<IP-du-PC>:8443` (voir `Dockerfile`, `docker/apache-ssl.conf`). Ouvrir FactuPro sur le PC via `https://<IP-du-PC>:8443` avant de générer le QR de scan mobile ; accepter l'avertissement de certificat non reconnu sur le téléphone (normal pour un certificat auto-signé, à faire une seule fois).

**Sous Laragon natif**, activer HTTPS manuellement : menu Laragon → clic droit → Apache → SSL (ou "Auto HTTPS") ; Laragon génère alors son propre certificat local.

Sans HTTPS, l'appli reste pleinement utilisable — seule la caméra du téléphone est indisponible : un champ de saisie manuelle du code-barres reste toujours proposé sur `scanner_mobile.php` (cf. `views/scanner_mobile/index.php`) et le PC affiche un avertissement explicite au moment de générer le QR (cf. `ScanSessionController::createSession()`).

## Sauvegarde de la base

`php database/backup.php` (ou `docker exec facturation_app php database/backup.php` pour le conteneur) génère un dump horodaté dans `database/backups/` (non versionné). Lit les identifiants depuis `.env` — valable pour l'environnement où la commande est lancée.
