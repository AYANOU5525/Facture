# 📚 FACTUPRO - Gestion de Facturation avec Module B2B

**Projet BTS - Version Simplifiée et Maîtrisée**  
**6 Tables | 16 Fichiers | 100% Maîtrisé**

---

## 🎯 DESCRIPTION DU PROJET

**FactuPro** est une application web de gestion de facturation destinée aux PME, intégrant un module innovant **B2B Connect** permettant aux entreprises de commercer entre elles avec **gestion automatique des stocks**.

### Problématique Résolue

Les entreprises utilisent souvent plusieurs outils pour :
- Gérer leurs stocks et factures
- Trouver des fournisseurs
- Passer des commandes B2B

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
8. ✅ Annonces (appels d'offres, partenariats)
9. ✅ Commandes inter-entreprises
10. ✅ **Flux de stock automatique entre entreprises** ⭐

---

## 🗄️ STRUCTURE DE LA BASE DE DONNÉES

### 6 Tables Simplifiées

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
```

### Schéma Relationnel

```
                    ENTREPRISE
                        │
        ┌───────────────┼───────────────┬──────────────┐
        │               │               │              │
        ▼ N             ▼ N             ▼ N            ▼ N
   UTILISATEUR      PRODUIT         VENTE         ANNONCE
                                                       
              ENTREPRISE (Relations B2B)
                        │
                ┌───────┴───────┐
                │               │
                ▼ Acheteuse     ▼ Vendeuse
            COMMANDE_B2B
```

---

## 📁 STRUCTURE DES FICHIERS (13 fichiers principaux)

### Configuration (2)
- `db.php` - Connexion PDO à la base de données
- `auth.php` - Gestion de l'authentification

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

### Module B2B (3)
- `reseau_b2b.php` - Annuaire des entreprises
- `annonces.php` - Gestion des annonces
- `commandes_b2b.php` - Commandes B2B (Création & Suivi)

### Base de Données (3)
- `facturation.sql` - Schéma
- `installation_complete.sql` - Installation auto
- `reset_donnees_test.sql` - Données de démo

---

## 🚀 INSTALLATION

### Prérequis
- XAMPP ou Laragon
- PHP 7.4+
- MySQL / MariaDB

### Étapes d'Installation

#### 1. Créer la base de données
```sql
CREATE DATABASE facturation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 2. Importer le schéma
- Ouvrir phpMyAdmin
- Sélectionner la base `facturation`
- Importer le fichier `facturation.sql`
- Vérifier que les 6 tables sont créées

#### 3. Charger les données de test (optionnel)
- Importer le fichier `reset_donnees_test.sql`
- Cela créera :
  - 2 entreprises (FourniPro, MaBoutique)
  - 2 utilisateurs (admin_fourni, admin_boutique)
  - 4 produits (dont 2 en déstockage B2B)

#### 4. Configuration
Vérifier `db.php` :
```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=facturation;charset=utf8mb4',
    'root',
    ''
);
```

#### 5. Accéder à l'application
- URL : `http://localhost/facturation`
- Créer un compte ou utiliser les comptes de test :
  - **FourniPro** : `admin_fourni` / `admin123`
  - **MaBoutique** : `admin_boutique` / `admin123`

---

## 💡 POINTS TECHNIQUES CLÉS

### 1. Stockage JSON (La "Botte Secrète")

**Pourquoi JSON ?**
> "Je stocke les articles en JSON pour **figer les prix au moment de la vente**. Si le prix d'un produit change demain, ma facture passée reste correcte. C'est un snapshot de la transaction."

**Format** :
```json
[
  {
    "id_produit": 1,
    "nom": "Papier A4",
    "quantite": 10,
    "prix": 5000
  }
]
```

**Code** :
```php
// Encodage
$articles_json = json_encode($articles, JSON_UNESCAPED_UNICODE);

// Décodage
$articles = json_decode($vente['Articles_JSON'], true);
```

### 2. Mise à Jour Automatique du Stock ⭐

**Lors d'une vente** :
```php
foreach ($articles as $article) {
    $stmt = $pdo->prepare("
        UPDATE Produit 
        SET Quantite_En_Stock = Quantite_En_Stock - ? 
        WHERE Id_Produit = ?
    ");
    $stmt->execute([$article['quantite'], $article['id_produit']]);
}
```

**Lors de la validation d'une commande B2B** :
```php
// Même code, exécuté automatiquement lors de la validation
```

### 3. Mise à Jour Automatique du Score ⭐

**Lors de la livraison d'une commande B2B** :
```php
$stmt = $pdo->prepare("
    UPDATE Entreprise 
    SET Score_Fiabilite = Score_Fiabilite + 1,
        Nombre_Commandes_Completees = Nombre_Commandes_Completees + 1
    WHERE Id_Entreprise = ?
");
$stmt->execute([$id_vendeur]);
```

### 4. Sécurité

- **Anti-SQL Injection** : Requêtes préparées PDO
- **Mots de passe** : Hash bcrypt avec `password_hash()`
- **Anti-XSS** : Échappement HTML avec `htmlspecialchars()`
- **Sessions** : Authentification sécurisée
- **Isolation** : Chaque entreprise ne voit que ses données

---

## 🎬 WORKFLOW COMMANDE B2B

```
1. EN_ATTENTE
   ↓ (Vendeur valide)
   
2. VALIDEE → Stock mis à jour automatiquement ⭐
   ↓ (Vendeur expédie)
   
3. EXPEDIEE
   ↓ (Acheteur confirme)
   
4. LIVREE → Score vendeur +1 automatiquement ⭐

Alternative : REFUSEE (Vendeur refuse)
```

---

## 📊 DÉMONSTRATION (10 MINUTES)

### Préparation
- 2 navigateurs : Chrome (FourniPro) + Firefox (MaBoutique)
- Connexions prêtes

### Scénario

**1. Introduction (1 min)** - Chrome
- Dashboard FourniPro
- Expliquer le concept

**2. Produits (1 min)** - Chrome
- Voir les produits
- Montrer Papier A4 en déstockage B2B (Stock: 150)

**3. Annuaire B2B (1 min)** - Firefox
- MaBoutique voit FourniPro
- Score 100/100
- Liens email/téléphone

**4. Commande B2B (5 min)** ⭐ **CŒUR DE LA DÉMO**

**4.1 Création** - Firefox
- Aller dans le menu "**Commandes B2B**"
- Voir la section "**Passer une nouvelle commande**"
- Choisir "FourniPro" dans la liste
- Liste des produits B2B apparaît
- Choisir **20** ramettes de Papier A4 (Stock visible: 150)
- Cliquer sur "Envoyer la commande"
- Montant : 20 × 4500 = 90 000 FCFA

**4.2 Validation** - Chrome
- FourniPro valide la commande
- **MONTRER : Stock passe de 150 à 130** ⭐

**4.3 Expédition** - Chrome
- Marquer comme "Expédiée"

**4.4 Livraison** - Firefox
- MaBoutique confirme la réception
- **MONTRER : Score FourniPro passe à 101** ⭐

**5. Conclusion (1 min)**
- Récapituler la gestion automatique
- Insister sur la valeur métier

---

## 💬 QUESTIONS/RÉPONSES POUR LE JURY

### Q1 : "Pourquoi seulement 6 tables ?"

**Réponse** :
> "J'ai appliqué le principe KISS (Keep It Simple). Chaque table a un rôle clair. J'ai fusionné certaines tables pour éviter la redondance :
> - Score intégré dans Entreprise (pas de table séparée)
> - Déstockage intégré dans Produit
> - Vente unifie Facture et Vente
> 
> Cette structure est normalisée (3NF) tout en restant simple à maintenir."

### Q2 : "Pourquoi stocker en JSON ?"

**Réponse** :
> "Pour **figer les prix au moment de la vente**. Si le prix d'un produit change demain, ma facture passée reste correcte. C'est un snapshot de la transaction.
> 
> De plus, cela simplifie le code : pas besoin de table Ligne_Vente avec des jointures complexes. Pour un projet BTS, c'est un bon compromis entre normalisation et pragmatisme."

### Q3 : "Comment fonctionne la mise à jour automatique du stock ?"

**Réponse** :
> "Lors de la validation d'une commande B2B :
> 1. Le vendeur clique sur 'Valider'
> 2. Mon code parcourt les articles (stockés en JSON)
> 3. Pour chaque article, j'exécute : `UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock - ?`
> 4. Le stock est mis à jour en temps réel
> 
> C'est automatique, fiable et évite les erreurs humaines."

### Q4 : "Pourquoi pas de messagerie ?"

**Réponse** :
> "La messagerie en temps réel nécessite du JavaScript asynchrone (AJAX/WebSocket) qui dépasse le cadre du BTS. Les entreprises peuvent se contacter par email ou téléphone via les coordonnées affichées dans l'annuaire. J'ai préféré me concentrer sur le cœur métier : la gestion fiable des transactions B2B."

---

## 🎯 SIMPLIFICATIONS PAR RAPPORT À UNE VERSION COMPLÈTE

### Ce qui a été fusionné/simplifié

| Avant | Après | Raison |
|-------|-------|--------|
| Table Score_Fiabilite | Colonnes dans Entreprise | Éviter une table pour une seule valeur |
| Table Facture + Table Vente | Table Vente unique | Une facture est une vente formalisée |
| Annonce "déstockage" | Colonnes dans Produit | Le déstockage concerne un produit existant |
| Table Message | Liens email/téléphone | Messagerie temps réel trop complexe pour BTS |

### Avantages

✅ **Moins de tables** = Plus facile à expliquer  
✅ **Moins de jointures** = Requêtes plus simples  
✅ **Code plus clair** = Maîtrise à 100%  
✅ **Parfait pour BTS** = Complexité adaptée  

---

## 📈 STATISTIQUES DU PROJET

| Métrique | Valeur |
|----------|--------|
| **Tables** | 6 |
| **Fichiers PHP** | 13 |
| **Fichiers SQL** | 2 |
| **Fichiers CSS** | 1 |
| **Fonctionnalités** | 10 |
| **Relations BDD** | 8 |
| **Niveau de maîtrise** | 100% ✅ |

---

## 🔧 TECHNOLOGIES UTILISÉES

- **Backend** : PHP 7.4+ (natif, sans framework)
- **Base de données** : MySQL / MariaDB
- **Frontend** : HTML5, CSS3
- **Icônes** : Font Awesome
- **Sécurité** : PDO, bcrypt, htmlspecialchars

---

## 📝 CODE SIMPLE ET LISIBLE

Le code est volontairement simple et commenté pour être compréhensible par un étudiant BTS :
- Pas de frameworks complexes
- PHP natif avec PDO
- Commentaires explicatifs
- Structure claire

---

## 🎓 PROJET BTS

Ce projet est conçu pour être présenté en BTS :
- ✅ Code clair et commenté
- ✅ Architecture simple (6 tables)
- ✅ Fonctionnalités essentielles
- ✅ Documentation complète
- ✅ Démonstration fluide (10 min)
- ✅ Arguments solides pour le jury

---

## 🏆 POINTS FORTS À METTRE EN AVANT

### 1. Innovation
Premier logiciel de gestion avec réseau B2B intégré pour PME

### 2. Automatisation
- Mise à jour automatique du stock ⭐
- Calcul automatique du score de fiabilité ⭐
- Génération dynamique des factures

### 3. Sécurité
- Requêtes préparées (anti-SQL injection)
- Hash bcrypt pour mots de passe
- Échappement HTML (anti-XSS)

### 4. Valeur Métier
- Gain de temps pour les entreprises
- Réduction des erreurs (automatisation)
- Traçabilité complète
- Système de confiance entre entreprises

---

## 🚀 AMÉLIORATIONS FUTURES POSSIBLES

### Court terme
- Upload de logo d'entreprise
- Export PDF des factures
- Notifications par email

### Moyen terme
- Prix dégressifs automatiques
- Statistiques avancées avec graphiques
- Gestion des stocks avec alertes

### Long terme
- Application mobile (API REST)
- Système de paiement en ligne
- Intelligence artificielle pour recommandations

---

## ✅ CHECKLIST AVANT SOUTENANCE

### Préparation Technique
- [ ] Base de données `facturation` créée
- [ ] 6 tables présentes
- [ ] Données de test chargées
- [ ] 2 navigateurs prêts (Chrome + Firefox)
- [ ] Connexions testées

### Préparation Personnelle
- [ ] Démo répétée 3 fois minimum
- [ ] Arguments JSON maîtrisés
- [ ] Réponses aux questions préparées
- [ ] README.md imprimé
- [ ] Schéma BDD imprimé

### Fonctionnalités
- [ ] Stock se met à jour automatiquement
- [ ] Score se met à jour automatiquement
- [ ] Liens email/téléphone fonctionnels
- [ ] Workflow complet testé

---

## 📞 SUPPORT

Pour toute question sur le projet :
1. Consulter ce README.md
2. Vérifier `PLAN_ACTION_FINAL.md`
3. Tester avec `reset_donnees_test.sql`

---

## 🎯 CONCLUSION

**FactuPro** est un projet BTS :
- ✅ **Simple** : 6 tables, 16 fichiers
- ✅ **Maîtrisé** : Vous connaissez chaque ligne
- ✅ **Fonctionnel** : 10 fonctionnalités solides
- ✅ **Innovant** : Module B2B avec gestion automatique
- ✅ **Sécurisé** : Bonnes pratiques appliquées
- ✅ **Prêt** : Documentation complète

**Bonne chance pour votre soutenance ! 🍀**

---

**Projet créé le** : Janvier 2026  
**Version** : 1.0 Simplifiée  
**Niveau** : BTS SIO / BTS Informatique  
**Base de données** : 6 tables (facturation.sql)  
**Fichiers** : 16 fichiers PHP/CSS/SQL
