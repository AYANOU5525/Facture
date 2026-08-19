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
