# 🛠️ Stack Technique & Outils du Projet FactuPro

Ce document récapitule l'ensemble des technologies, langages et concepts techniques mis en œuvre pour réaliser l'application **FactuPro B2B**.

---

## 1. 💻 Langages & Cœur du Système

### **Backend : PHP (Vanilla)**
Nous avons fait le choix de ne pas utiliser de framework lourd (comme Symfony ou Laravel) pour **prouver la maîtrise des fondamentaux** du développement Web.
*   **Version** : PHP 8.x
*   **Architecture** : MVC Simplifié (Modèle-Vue-Contrôleur adapté).
*   **Sécurité** :
    *   Utilisation stricte de `PDO` (PHP Data Objects) pour l'accès aux données.
    *   Requêtes préparées (`prepare` / `execute`) pour bloquer les injections SQL.
    *   Protection XSS via `htmlspecialchars()` sur tous les affichages.
    *   Hachage des mots de passe avec `password_hash()` (Algorithme Bcrypt/Argon2).
    *   Gestion des sessions `session_start()` sécurisées.

### **Base de Données : MariaDB / MySQL**
*   **Structure** : Relationnelle (6 Tables clés : Utilisateur, Entreprise, Produit, Vente, Commande_B2B, Message).
*   **Relations** : Clés étrangères (`FOREIGN KEY`) avec contraintes `ON DELETE CASCADE` ou `SET NULL`.
*   **Transactions** : Utilisation de `beginTransaction()`, `commit()` et `rollBack()` lors des ventes pour garantir que le stock est décrémenté uniquement si la vente est validée (Principe ACID).

---

## 2. 🎨 Frontend & Design (UI/UX)

### **CSS 3 (Vanilla - Sur mesure)**
Pas de framework CSS (Bootstrap/Tailwind) pour le style principal, afin de créer une identité visuelle unique "Premium".
*   **Design System** : Création de variables CSS (`:root`) pour la palette de couleurs (Bleu Nuit, Accents Vibrants, Gris).
*   **Glassmorphism** : Effets de transparence et de flou sur les cartes.
*   **Grid & Flexbox** : Mise en page 100% responsive (adaptée mobile/tablette/desktop).
*   **Animations** : Keyframes pour les apparitions en fondu (`fade-in`) au chargement des pages.

### **JavaScript (ES6+)**
Utilisé pour dynamiser l'interface sans recharger la page.
*   **AJAX (Fetch API)** : Pour le rechargement automatique de la messagerie instantanée toutes les 2 secondes (`setInterval`).
*   **DOM Manipulation** : Pour la gestion intelligente du formulaire de vente (griser les produits déjà sélectionnés pour éviter les doublons).

### **Ressources Externes**
*   **Icônes** : [FontAwesome 6](https://fontawesome.com/) (CDN) pour l'iconographie vectorielle.
*   **Polices** : [Google Fonts](https://fonts.google.com/) (Combinaison de *Poppins* pour les titres et *Inter* pour la lisibilité du texte).

---

## 3. ⚙️ Fonctionnalités Techniques Avancées

### **1. Module B2B & Workflow de Commande**
Implémentation d'un flux d'état complet : `En attente` -> `Validée` -> `Expédiée` -> `Livrée`.
*   **Mise à jour automatique** : Lorsqu'une commande est "Expédiée" par le fournisseur, le système génère **automatiquement** une facture dans la table `Vente` et décrémente le stock.

### **2. Messagerie Instantanée (Chat)**
Système de chat interne aux entreprises.
*   Logique de rafraîchissement asynchrone (AJAX) pour simuler du temps réel sans WebSocket (solution robuste et simple pour hébergement mutualisé).

### **3. Gestion des Rôles (Sudo Mode)**
Sécurisation des actions critiques (changement de rôle admin).
*   Requiert une **ré-authentification** par mot de passe avant validation, même si l'utilisateur est déjà connecté.

### **4. Stockage de Données Complexes (JSON)**
Utilisation du format `JSON` dans la base de données (colonnes ` Articles_JSON`) pour stocker le détail des lignes d'une facture.
*   Permet de garder l'historique exact des prix et noms des produits au moment de la vente, même si le produit est modifié ou supprimé plus tard.

---

## 4. 🖥️ Environnement de Développement

*   **Serveur Local** : Laragon (Apache + MySQL + PHP).
*   **Éditeur de code** : Visual Studio Code.
*   **Versionning** : Git (Gestion de l'historique des modifications).
*   **Client SQL** : HeidiSQL (intégré à Laragon) pour la visualisation des tables.

---

## 5. 📊 Résumé des Tables (Base de Données)

1.  **Utilisateur** : Comptes de connexion (Admins/Utilisateurs).
2.  **Entreprise** : Identité des sociétés (Vendeurs/Acheteurs).
3.  **Produit** : Catalogue et gestion des stocks.
4.  **Vente** : Historique des factures clients (JSON).
5.  **Commande_B2B** : Flux d'achat entre entreprises.
6.  **Message** : Échanges textuels entre entreprises.

---

*Ce document atteste de la variété des compétences techniques déployées pour la réalisation de FactuPro.*
