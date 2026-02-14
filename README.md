# 📚 FACTUPRO - Gestion de Facturation avec Module B2B (WebSocket & Env)

**Projet BTS - Version Simplifiée et Maîtrisée**  
**6 Tables | 20 Fichiers | 100% Maîtrisé | WebSocket Chat**

---

## 🎯 DESCRIPTION DU PROJET

**FactuPro** est une application web de gestion de facturation destinée aux PME, intégrant un module innovant **B2B Connect** permettant aux entreprises de commercer entre elles avec **gestion automatique des stocks**. Cette version intègre désormais une messagerie en temps réel et une gestion sécurisée des configurations.

### Problématique Résolue

Les entreprises utilisent souvent plusieurs outils pour :
- Gérer leurs stocks et factures
- Trouver des fournisseurs
- Passer des commandes B2B
- Discuter avec leurs partenaires

**Solution** : FactuPro centralise tout en une seule application.

---

## ⭐ FONCTIONNALITÉS PRINCIPALES

### Gestion de Base
1. ✅ Authentification sécurisée (bcrypt)
2. ✅ Gestion des produits (CRUD)
3. ✅ Création de ventes/factures
4. ✅ **Mise à jour automatique du stock** ⭐
5. ✅ Tableau de bord avec statistiques

### Module B2B Connect
6. ✅ Annuaire des entreprises avec scores de fiabilité
7. ✅ Produits en déstockage B2B
8. ✅ Commandes inter-entreprises
9. ✅ **Messagerie Instantanée (WebSocket)** ⭐ (Nouveau)
10. ✅ **Flux de stock automatique entre entreprises** ⭐

---

## 🗄️ STRUCTURE DE LA BASE DE DONNÉES

### 7 Tables (dont 1 pour la messagerie)
*Note : Le projet utilise principalement 6 tables métier + 1 table technique Message*

```
1. ENTREPRISE (avec score intégré)
   - Infos de base + NIF, secteur, description
   - Score_Fiabilite (0-100)
   - Nombre_Commandes_Completees

2. UTILISATEUR
   - Authentification avec hash bcrypt
   - Rôle admin ou utilisateur

3. PRODUIT (avec option déstockage B2B)
   - Catalogue avec prix et stock
   - En_Destockage_B2B (boolean)
   - Prix_B2B, Quantite_Min_B2B

4. VENTE (factures unifiées)
   - Ventes et factures en une seule table
   - Articles_JSON (format JSON)
   - Type_Vente (directe/b2b)

5. ANNONCE
   - Appels d'offres et partenariats
   - Statut (active/expiree/terminee)

6. COMMANDE_B2B (cœur du système)
   - Transactions inter-entreprises
   - Statut (en_attente → validee → expediee → livree)
   - Articles_JSON

7. MESSAGE
   - Messagerie interne
   - Id_Expediteur, Id_Destinataire
   - Contenu, Date_Envoi, Lu
```

### Schéma Relationnel

```
                    ENTREPRISE
                        │
        ┌───────┬───────┼───────────────┬──────────────┐
        │       │       │               │              │
        ▼       ▼ N     ▼ N             ▼ N            ▼ N
    MESSAGE  UTILISATEUR PRODUIT       VENTE         ANNONCE
   (Emet/Reçoit)                                                       
              ENTREPRISE (Relations B2B)
                        │
                ┌───────┴───────┐
                │               │
                ▼ Acheteuse     ▼ Vendeuse
            COMMANDE_B2B
```

---

## 📁 STRUCTURE DES FICHIERS

### Configuration & Environnement (3)
- `.env` - Variables d'environnement (Base de données, etc.) **(Nouveau)**
- `.env.example` - Modèle de fichier .env
- `db.php` - Connexion PDO via phpdotenv

### WebSocket Server (2)
- `bin/chat-server.php` - Serveur WebSocket (Ratchet)
- `src/Chat.php` - Logique de la messagerie instantanée

### Interface (2)
- `header.php` - Navigation et en-tête (inclut le footer)
- `style.css` - Feuille de style CSS

### Pages Principales (5)
- `index.php` - Point d'entrée
- `login.php` - Connexion
- `register.php` - Inscription
- `dashboard.php` - Tableau de bord
- `logout.php` - Déconnexion

### Gestion (3)
- `products.php` - Gestion des produits (CRUD intégré)
- `sales.php` - Historique des ventes/factures
- `invoice_add.php` - Créer une vente/facture

### Module B2B & Messagerie (3)
- `reseau_b2b.php` - Annuaire des entreprises
- `messages.php` - Messagerie WebSocket (Client)
- `commandes_b2b.php` - Commandes B2B (Création & Suivi)

### Base de Données (2)
- `facturation.sql` - Schéma
- `reset_donnees_test.sql` - Données de démo

---

## 🚀 INSTALLATION

### Prérequis
- XAMPP ou Laragon
- PHP 8.0+
- MySQL / MariaDB
- Composer (Gestionnaire de dépendances)

### Étapes d'Installation

#### 1. Cloner et Installer Dépendances
```bash
git clone [URL_REPO]
cd facturation
composer install
```

#### 2. Configurer l'Environnement
- Copiez le fichier `.env.example` en `.env` :
- Modifiez `.env` avec vos paramètres BDD :
```ini
DB_HOST=localhost
DB_NAME=facturation
DB_USER=root
DB_PASS=
```

#### 3. Base de Données
- Importez `facturation.sql` dans votre SGBD.
- Importez `reset_donnees_test.sql` pour avoir des données.

#### 4. Lancer le Serveur WebSocket
Pour que la messagerie fonctionne, lancez dans un terminal séparé :
```bash
php bin/chat-server.php
```
*Le serveur écoutera sur le port 8080.*

#### 5. Accéder à l'application
- URL : `http://localhost/facturation`
- Comptes de test :
  - **FourniPro** : `admin_fourni` / `admin123`
  - **MaBoutique** : `admin_boutique` / `admin123`

---

## 💡 POINTS TECHNIQUES CLÉS

### 1. Variables d'Environnement (.env)
Utilisation de la bibliothèque `vlucas/phpdotenv` pour sécuriser les identifiants en dehors du code source.

### 2. WebSocket & Ratchet (Messagerie)
La messagerie utilise le protocole **WebSocket** (via la librairie `cboden/ratchet`) pour une communication bidirectionnelle temps réel.
- **Client (JS)** : Ouvre une connexion `ws://` et envoie/reçoit des messages JSON.
- **Serveur (PHP)** : Gère les connexions, authentifie les clients par leur ID Session, et route les messages.

### 3. Stockage JSON (La "Botte Secrète")
**Pourquoi JSON ?**
Pour **figer les prix au moment de la vente**. C'est un snapshot de la transaction.
```php
$articles_json = json_encode($articles, JSON_UNESCAPED_UNICODE);
```

### 4. Mise à Jour Automatique du Stock ⭐
Lors de la validation d'une commande B2B, le stock est décrémenté automatiquement. Cela garantit la cohérence des inventaires entre partenaires.

---

## � DÉMONSTRATION (MESSAGERIE)

1. **Ouvrir 2 Navigateurs** : L'un connecté en tant que FourniPro, l'autre MaBoutique.
2. **Lancer le serveur chat** : `php bin/chat-server.php`
3. **FourniPro** : Va dans "Réseau B2B", clique sur "Discuter" avec MaBoutique.
4. **MaBoutique** : Fait de même.
5. **Envoyer un message** : Le message apparaît **instantanément** sur l'autre écran sans recharger la page.
6. **Persistence** : Si on ferme et rouvre, l'historique est chargé depuis la base de données.

---

## ✅ CHECKLIST SOUTENANCE

- [ ] `.env` configuré et non-commité
- [ ] `composer install` effectué
- [ ] Serveur WebSocket lancé (`php bin/chat-server.php`)
- [ ] Base de données importée
- [ ] Démonstration Messagerie Temps Réel prête

---

## 📞 SUPPORT

1. Consulter ce README.md
2. Vérifier que le serveur WebSocket tourne
3. Vérifier les logs PHP si besoin

---

**Bonne chance pour votre soutenance ! 🍀**
