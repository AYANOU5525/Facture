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

- **Authentification & Droits** : chiffrement des mots de passe avec `bcrypt` et gestion des rôles (`Admin` / `Utilisateur`).
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

Le schéma complet est réparti en un fichier principal et des migrations correctives, **à exécuter dans cet ordre** sur une base neuve :

1. `database/facturation.sql` — schéma de base + données de démonstration.
2. `database/migration_correction_mcd.sql` — ajoute `Ligne_Vente`, `Vente.Id_Vendeur` et les triggers de `Montant_Total`. **Indispensable** : sans cette étape, toute vente échoue avec `SQLSTATE[42S22]: Unknown column 'Id_Vendeur'`.
3. `database/migration_index_notifications.sql` — index de performance sur `Notification_B2B`.
4. `database/migration_index_barcode.sql` — index de performance sur les codes-barres produits.

```bash
mysql -u root --default-character-set=utf8mb4 facturation < database/facturation.sql
mysql -u root --default-character-set=utf8mb4 facturation < database/migration_correction_mcd.sql
mysql -u root --default-character-set=utf8mb4 facturation < database/migration_index_notifications.sql
mysql -u root --default-character-set=utf8mb4 facturation < database/migration_index_barcode.sql
```

`--default-character-set=utf8mb4` évite que le client `mysql` retombe sur un jeu de caractères par défaut (ex. `cp850` sous Windows) et corrompe les caractères accentués des commentaires de colonnes lors de l'import.

Pour réinitialiser les données de démonstration ensuite (comptes de test à mot de passe connu) : `php database/reset_demo.php`.
