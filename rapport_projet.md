# 📋 Rapport Complet — Projet FactuPro

> **Date du rapport** : 25 août 2026
> **Chemin du projet** : `c:\laragon\www\facturation`
> **Type** : Application Web PHP — Gestion de facturation & réseau B2B
> **Portée de ce rapport** : audit complet du code, de la sécurité et de l'infrastructure, après une session de travail approfondie (sécurité, RBAC, système code-barres/carton, refontes UI, portabilité Linux/Docker)

---

## 1. 🎯 Vue d'ensemble

**FactuPro** est une application de gestion de facturation et de commerce inter-entreprises (B2B) pour PME : stocks, ventes, factures, logistique et commandes B2B dans une seule interface, avec isolation multi-entreprise (chaque entreprise ne voit que ses propres données).

| Élément | Valeur |
|---|---|
| **Langage** | PHP 8.2/8.3 |
| **Base de données** | MySQL 8.x (via PDO, requêtes préparées partout) |
| **Environnement** | Laragon (local, Windows) + Docker (`docker-compose.yml`, testé sous Linux) |
| **Exposition externe** | Tunnel ngrok (`https://trousers-nickname-outrank.ngrok-free.dev`) |
| **Dépendances** | `vlucas/phpdotenv`, `phpmailer/phpmailer` |
| **Rôles** | `admin` (plateforme), `proprio`, `vendeur`, `livreur` — RBAC centralisé |
| **Sécurité sessions** | Timeout 15 min inactivité, CSRF à usage unique, bcrypt, verrou anti-bruteforce (5 tentatives/15 min) |

---

## 2. ⚠️ État du dépôt — IMPORTANT

**Rien n'est commité.** 40 fichiers modifiés + 4 nouveaux fichiers non trackés, tous depuis le dernier commit (`76ac1ca — Refonte UX des tableaux de bord et pages logistique`, 20 août). C'est le travail de toute la session en cours de sécurité, RBAC, code-barres et refonte UI.

```
 M  .env.example, api/*.php (3), assets/css/style.css, database/facturation.sql
 M  includes/{auth,b2b_helpers,csrf,error_handler,header,roles}.php
 D  includes/commandes_b2b.php          (doublon mort supprimé)
 M  pages/*.php (25 fichiers)
 M  src/Application/{Billing,Inventory}/*.php, src/Infrastructure/Persistence/*.php
 ?? "Nouveau Document texte.txt"        (bloc-notes personnel — à committer ou supprimer)
 ?? assets/css/animations.css           (nouveau, utilisé par header.php)
 ?? database/reset_demo.php             (script de seed, CLI-only)
 ?? src/Application/Inventory/PackagingConverter.php  (nouveau service)
```

**Recommandation** : découper en commits logiques (sécurité / RBAC+renommage FR / code-barres-carton / UI) plutôt qu'un seul gros commit, pour garder un historique lisible.

---

## 3. 🔒 Sécurité — vulnérabilités trouvées et corrigées cette session

| # | Problème | Gravité | Statut |
|---|---|---|---|
| 1 | `pages/team.php` : n'importe quel `proprio` pouvait s'auto-promouvoir (ou promouvoir un employé) au rôle `admin` plateforme via le formulaire d'équipe — aucune validation serveur | 🔴 Critique | ✅ Corrigé — whitelist stricte `['proprio','vendeur','livreur']` |
| 2 | `database/reset_demo.php` accessible publiquement (aucune garde CLI, contrairement à `check_db.php`) — n'importe qui pouvait vider toute la BDD et la reseeder avec des mots de passe connus | 🔴 Critique | ✅ Corrigé — garde `PHP_SAPI !== 'cli'` ajoutée |
| 3 | `includes/error_handler.php` affichait la stack trace complète (chemins serveur, requêtes, code) à n'importe quel visiteur en cas d'exception | 🟠 Élevé | ✅ Corrigé — conditionné à `APP_DEBUG=true` |
| 4 | Portabilité Linux/Docker : `database/facturation.sql` créait les tables en minuscules alors que **tout le code** interroge en `PascalCase` — fonctionnait par accident sur Windows (MySQL insensible à la casse) mais aurait **totalement cassé** sur tout hébergement Linux classique | 🔴 Critique (déploiement) | ✅ Corrigé — 14 tables renommées dans le dump + `reset_demo.php` |
| 5 | Corruption du dump SQL (caractère `=` isolé cassant l'import, ligne `@@SQL_MODE` scindée par un retour à la ligne) | 🟡 Moyen | ✅ Corrigé |
| 6 | `InvoiceService::createDirectSale()` appliquait le facteur de conversion carton **deux fois** (prix ET quantité) — vente à 20× le bon prix | 🔴 Critique (intégrité financière) | ✅ Corrigé, testé (voir §5) |
| 7 | Le facteur de conversion carton était envoyé par le client et accepté tel quel côté serveur — falsifiable via devtools/requête modifiée | 🟠 Élevé | ✅ Corrigé — coefficient toujours recalculé serveur depuis le produit verrouillé en base |
| 8 | CSP bloquait silencieusement les tuiles de carte, icônes, géocodage et calcul d'itinéraire (Leaflet/OSM/Nominatim/OSRM) — la carte semblait "ne jamais charger" | 🟡 Moyen (fonctionnel) | ✅ Corrigé — domaines nécessaires ajoutés à `img-src`/`connect-src`/`style-src` |
| 9 | CSP/Permissions-Policy bloquaient le scanner caméra (`camera=()`, `unpkg.com` absent de `script-src`) | 🟡 Moyen (fonctionnel) | ✅ Corrigé |

### Points déjà solides (vérifiés, pas de régression)
- **CSRF** : présent sur les 15 pages qui traitent du POST, token à usage unique
- **Injections SQL** : aucune requête concaténée trouvée dans tout le projet — 100% requêtes préparées
- **`.env`** : correctement ignoré par git (`.gitignore`), jamais commité
- **Mots de passe** : `password_hash()`/`password_verify()` (bcrypt), jamais en clair en base
- **Isolation multi-entreprise** : chaque requête filtre par `Id_Entreprise` de la session

---

## 4. 👥 RBAC — Système de rôles

Quatre rôles (`includes/roles.php`) : `admin` (plateforme, aucune entreprise), `proprio` (accès complet à son entreprise), `vendeur` (ventes/clients/factures, stock en lecture), `livreur` (logistique uniquement).

**Architecture** : chaque page utilise `exigerPermission(peutX())` — une fonction métier centralisée — plutôt qu'une liste de rôles recopiée à chaque page. Ce pattern a directement empêché la récidive du bug #1 ci-dessus : modifier une permission se fait dans `roles.php` uniquement, jamais page par page.

Deux fonctions restent définies mais jamais appelées (`peutGererExpeditions`, `peutGererPlateforme`) — normal : aucune page de gestion manuelle d'expédition ni de panneau admin-plateforme n'existe encore. Prêtes pour ces futures fonctionnalités.

**Toutes les fonctions et le code `includes/`/`src/` sont en français** (renommage complet effectué cette session — `hasRole→aRole`, `requireRole→exigerRole`, `csrfToken→jetonCsrf`, etc. — 28 fonctions), cohérent avec le reste du code déjà francophone.

---

## 5. 📦 Système code-barres & carton (nouveau, construit cette session)

### Principe
- `Code_Barre_Unite` / `Code_Barre_Carton` / `Quantite_Par_Carton` sur `Produit` ; `Quantite_En_Stock` toujours en unités.
- Scanner = identification uniquement, ne touche jamais au stock. Le stock ne bouge qu'à la validation réelle (vente ou approvisionnement).
- **Mélange carton + unité** possible sur une même ligne (ex : 2 cartons + 5 unités).

### Architecture
- **`src/Application/Inventory/PackagingConverter.php`** (nouveau) — point unique de conversion (`toUnits`, `combinedUnits`, `detect`), réutilisé par `InvoiceService`, `StockService` et `api/lookup_product.php`. Aucune logique dupliquée.
- **Sécurité** : le coefficient de conversion est **toujours** recalculé côté serveur depuis `Quantite_Par_Carton` du produit verrouillé en base (`FOR UPDATE`) — jamais depuis une valeur envoyée par le client.
- **`api/lookup_product.php`** retourne explicitement `type_conditionnement` (`unite`/`carton`) et `coefficient`, déterminés serveur.

### Tests réels effectués (HTTPS, données nettoyées après coup)
| Scénario | Résultat |
|---|---|
| Vente à l'unité seule | ✅ |
| Vente au carton seul | ✅ |
| Mélange carton + unité sur une ligne | ✅ |
| Stock insuffisant → rejet, transaction annulée (aucune vente ni décrément orphelins) | ✅ |
| Entrée en stock (approvisionnement), mélange | ✅ |
| Falsification du coefficient carton côté client | ✅ Neutralisée (serveur autoritaire) |
| Réception B2B → produit auto-créé chez l'acheteur avec code-barre du vendeur copié | ✅ |

**Aucune migration SQL requise** — les colonnes existaient déjà, seule la logique applicative manquait.

---

## 6. 🎨 Interface & Design System

- **Mode sombre** : variable `--bg-card` etc. déjà en place ; plusieurs fonds blancs codés en dur (`background: white`/`#fff`) qui restaient figés en mode sombre ont été corrigés sur `annonces.php`, `dashboard.php`, `notifications_b2b.php`, `reseau_b2b.php`, `team.php`, `settings.php` (le dernier trouvé et corrigé pendant cet audit).
- **Menu utilisateur** : fond translucide (`glassmorphism`) rendu opaque.
- **`products.php`** : formulaire produit réorganisé (sections encadrées codes-barres/B2B, groupes champ+bouton scanner soudés), espacement carte/bordure corrigé.
- **`approvisionnement.php`** : lignes d'articles passées de styles inline bruts à une grille CSS cohérente avec le reste de l'app.
- **Scanner caméra** (3 pages) : bug de mise en page corrigé (vidéo sans limite de hauteur pouvait pousser les boutons hors écran) + bug JS corrigé (fermeture de la modale garantie même si la caméra échoue à démarrer — `NotFoundError` etc., prouvé par test).

---

## 7. 🏗️ Infrastructure

- **Docker** : `docker-compose.yml` (app + MySQL + phpMyAdmin) fonctionnel, base reconstruite avec le schéma corrigé (casse PascalCase), reseedée avec les mêmes comptes que le local.
- **13 comptes de démonstration**, 2 entreprises, tous les rôles représentés plusieurs fois (mots de passe individuels, plus de mot de passe partagé).
- **ngrok** : tunnel HTTPS actif et fonctionnel, domaine réservé stable.
- **⚠️ Disque C: quasi plein** : `227 Go / 231 Go utilisés (99%), 3.7 Go disponibles`. Pas causé par ce projet (Docker Desktop, VS Code, etc. y contribuent), mais à surveiller — un disque plein peut faire échouer des écritures MySQL/Docker sans avertissement clair.

---

## 8. 🧹 Qualité de code

- **Lint complet** : 59 fichiers PHP, **0 erreur de syntaxe**.
- **Aucun secret en dur**, aucun `var_dump`/`print_r` de debug oublié.
- **`uploads/chat_b2b/`** : toujours sans `.htaccess` pour désactiver l'exécution de scripts en défense en profondeur (whitelist d'extensions déjà en place côté PHP, donc pas critique, mais recommandé).
- **Dépendances Composer** : à jour à une version mineure près (`phpstan/phpstan`, `vlucas/phpdotenv`) — non urgent.
- **`Nouveau Document texte.txt`** : bloc-notes personnel de tâches, ne devrait pas rester dans le dépôt versionné.

---

## 9. 📌 Recommandations restantes (non bloquantes)

1. **Committer le travail** de cette session en plusieurs commits logiques avant de continuer.
2. Décider du sort de `Nouveau Document texte.txt` (déplacer hors repo ou committer si c'est voulu).
3. Ajouter un `.htaccess` (ou équivalent Nginx) dans `uploads/` pour interdire l'exécution de scripts, en plus de la whitelist d'extensions existante.
4. Libérer de l'espace sur le disque C: (Docker Desktop et VS Code ont chacun des centaines de Mo de fichiers temporaires nettoyables).
5. Les items de `Nouveau Document texte.txt` encore pertinents (email de bienvenue employé — déjà fait ; scanner code-barre — fait cette session) peuvent être nettoyés du fichier.

---

## 10. 🗂️ Fichiers créés cette session

| Fichier | Rôle |
|---|---|
| `src/Application/Inventory/PackagingConverter.php` | Conversion unité/carton centralisée |
| `database/reset_demo.php` | Seed de démo (13 comptes, 2 entreprises, CLI-only) |
| `assets/css/animations.css` | Animations UI |

---

*Rapport généré par audit automatisé (lint complet + grep ciblé + tests fonctionnels réels en HTTPS) — pas une simple relecture, chaque point de sécurité listé en §3 et §5 a été vérifié par un test reproductible.*
