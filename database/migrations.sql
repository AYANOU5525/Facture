-- ============================================================================
-- FactuPro — Migrations (fichier unique)
--
-- Toutes les migrations appliquées après le schéma de base
-- (database/facturation.sql) vivent ici, dans l'ordre où elles doivent être
-- exécutées. Ne plus créer de nouveau fichier migration_xxx.sql : ajouter la
-- prochaine migration à la SUITE de ce fichier, avec un bloc "-- Migration —
-- <titre>" comme les précédents, et documenter l'ordre dans le sommaire
-- ci-dessous et dans README.md.
--
-- Toutes les migrations ci-dessous sont non destructives (CREATE TABLE IF NOT
-- EXISTS / ALTER TABLE ADD / backfill) : rejouer ce fichier sur une base déjà
-- à jour ne doit rien casser (les CREATE TABLE sont IF NOT EXISTS ; les ADD
-- COLUMN / ADD INDEX échoueront proprement si déjà présents — c'est attendu
-- sur une base déjà migrée, il ne faut alors rejouer que la portion manquante).
--
-- Sommaire (ordre d'exécution) :
--   1. Correction MCD — Ligne_Vente, Vente.Id_Vendeur, triggers Montant_Total
--   2. Index — Notification_B2B (Id_Entreprise_Destinataire, Est_Lue)
--   3. Index — codes-barres Produit
--   4. Commande_B2B — adresse de livraison (mode livraison B2B)
--   5. Client + Ligne_Produit/Contenir (alignement MLD mémoire de soutenance)
--   6. Confirmation d'email à l'inscription + verrouillage progressif du login
--
-- Rappel environnements (cf. README.md) : ce fichier doit être rejoué sur les
-- DEUX bases (MySQL natif Laragon port 3306 ET conteneur Docker port 3307) —
-- elles ne se synchronisent jamais automatiquement entre elles.
-- ============================================================================

USE `facturation`;

-- ============================================================================
-- 1) Migration corrective — FactuPro
--
-- Contexte : le MCD fourni (image.png) a servi de base à une revue (dossier
-- « Dossier FactuPro »), mais la comparaison avec le schéma réel
-- (database/facturation.sql) montre que la base a déjà évolué bien au-delà
-- du diagramme :
--   - Utilisateur, Audit_Log, Historique_Commande_B2B, Logistique,
--     Password_Reset, Scan_Session(_Scan) existent en réalité et n'étaient
--     pas sur le diagramme.
--   - Client et Ligne_Produit n'ont jamais été implémentés (à l'époque) :
--     Vente utilise un simple Nom_Client texte, et Produit n'a pas de
--     catégorie — traité plus loin par la migration 5.
--   - Les deux inversions de cardinalité repérées sur le diagramme
--     (Facturer, Envoyer) n'existent PAS dans le schéma réel : Facture a
--     déjà une FK Id_Entreprise directe, Chat_B2B.Id_Entreprise_Emetteur
--     est déjà NOT NULL. Rien à corriger ici, c'était une erreur de dessin.
--
-- Ce qui restait réellement à corriger, et que cette migration traite :
--   1. Vente n'a aucune ligne relationnelle (Articles_JSON est l'unique
--      source de vérité, non exploitable en SQL) — contrairement à
--      Commande_B2B qui a déjà Ligne_Commande_B2B.
--   2. Vente.Nom_Vendeur est une chaîne libre, jamais rattachée à
--      Utilisateur — un renommage ou une faute de frappe casse le suivi
--      par vendeur.
--   3. Commande_B2B.Montant_Total et Vente.Montant_Total ne sont maintenus
--      que côté application : rien n'empêche un écart avec la somme réelle
--      des lignes.
--
-- **Indispensable** : sans cette étape, toute vente échoue avec
-- SQLSTATE[42S22]: Unknown column 'Id_Vendeur'.
-- ============================================================================

-- 1.1) Ligne_Vente — miroir de Ligne_Commande_B2B, corrige l'absence de lignes
--      relationnelles pour les ventes comptoir.

CREATE TABLE IF NOT EXISTS `Ligne_Vente` (
  `Id_Ligne_Vente` int NOT NULL AUTO_INCREMENT,
  `Id_Vente` int NOT NULL,
  `Id_Produit` int NOT NULL,
  `Nom_Produit` varchar(200) NOT NULL COMMENT 'Historisé : copie du nom au moment de la vente',
  `Quantite` int NOT NULL,
  `Prix_Unitaire` decimal(10,2) NOT NULL COMMENT 'Historisé au moment de la vente',
  `Sous_Total` decimal(10,2) GENERATED ALWAYS AS (`Quantite` * `Prix_Unitaire`) STORED,
  PRIMARY KEY (`Id_Ligne_Vente`),
  KEY `Id_Vente` (`Id_Vente`),
  KEY `Id_Produit` (`Id_Produit`),
  CONSTRAINT `ligne_vente_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `Vente` (`Id_Vente`) ON DELETE CASCADE,
  CONSTRAINT `ligne_vente_ibfk_2` FOREIGN KEY (`Id_Produit`) REFERENCES `Produit` (`Id_Produit`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Le JSON existant reste lisible (affichage, historique) mais n'est plus la
-- source de vérité pour les nouvelles ventes ; même traitement que
-- Commande_B2B.Articles_JSON, déjà marqué déprécié plus bas dans le dump.
ALTER TABLE `Vente`
  MODIFY COLUMN `Articles_JSON` text NULL
  COMMENT '[DEPRECATED] Remplacé par Ligne_Vente pour les nouvelles ventes ; conservé pour les ventes historiques et l''affichage.';

-- Pas de ré-import automatique de l'historique : le JSON des ventes 1-9 ne
-- porte pas toujours d'Id_Produit fiable (seulement un `nom`), un rapprochement
-- par nom serait risqué (produits renommés/doublons). Les nouvelles ventes
-- seules alimenteront Ligne_Vente une fois le code PHP mis à jour.

-- 1.2) Vente.Id_Vendeur — rattache enfin le vendeur à un compte Utilisateur.
--      Nom_Vendeur est conservé comme libellé d'affichage en cache.

ALTER TABLE `Vente`
  ADD COLUMN `Id_Vendeur` int DEFAULT NULL COMMENT 'FK Utilisateur ; Nom_Vendeur reste un libellé de cache' AFTER `Nom_Vendeur`,
  ADD KEY `idx_vente_vendeur` (`Id_Vendeur`),
  ADD CONSTRAINT `fk_vente_vendeur` FOREIGN KEY (`Id_Vendeur`) REFERENCES `Utilisateur` (`Id_Utilisateur`) ON DELETE SET NULL;

-- Backfill fiable : Nom_Vendeur stocke déjà exactement Utilisateur.Nom_Utilisateur
-- (ex. 'alex_admin', 'marie_tech') pour toutes les ventes existantes.
UPDATE `Vente` v
JOIN `Utilisateur` u ON u.`Nom_Utilisateur` = v.`Nom_Vendeur`
SET v.`Id_Vendeur` = u.`Id_Utilisateur`
WHERE v.`Id_Vendeur` IS NULL;

-- 1.3) Montant_Total auto-maintenu par trigger — pour Vente et Commande_B2B,
--      évite toute dérive entre le total stocké et la somme réelle des lignes.

DROP TRIGGER IF EXISTS `trg_ligne_vente_ins`;
DROP TRIGGER IF EXISTS `trg_ligne_vente_upd`;
DROP TRIGGER IF EXISTS `trg_ligne_vente_del`;
DROP TRIGGER IF EXISTS `trg_ligne_cmd_b2b_ins`;
DROP TRIGGER IF EXISTS `trg_ligne_cmd_b2b_upd`;
DROP TRIGGER IF EXISTS `trg_ligne_cmd_b2b_del`;

DELIMITER $$

CREATE TRIGGER `trg_ligne_vente_ins` AFTER INSERT ON `Ligne_Vente`
FOR EACH ROW
BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = NEW.`Id_Vente`
  ) WHERE `Id_Vente` = NEW.`Id_Vente`;
END$$

CREATE TRIGGER `trg_ligne_vente_upd` AFTER UPDATE ON `Ligne_Vente`
FOR EACH ROW
BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = NEW.`Id_Vente`
  ) WHERE `Id_Vente` = NEW.`Id_Vente`;
END$$

CREATE TRIGGER `trg_ligne_vente_del` AFTER DELETE ON `Ligne_Vente`
FOR EACH ROW
BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = OLD.`Id_Vente`
  ) WHERE `Id_Vente` = OLD.`Id_Vente`;
END$$

CREATE TRIGGER `trg_ligne_cmd_b2b_ins` AFTER INSERT ON `Ligne_Commande_B2B`
FOR EACH ROW
BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`;
END$$

CREATE TRIGGER `trg_ligne_cmd_b2b_upd` AFTER UPDATE ON `Ligne_Commande_B2B`
FOR EACH ROW
BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`;
END$$

CREATE TRIGGER `trg_ligne_cmd_b2b_del` AFTER DELETE ON `Ligne_Commande_B2B`
FOR EACH ROW
BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = OLD.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = OLD.`Id_Commande_B2B`;
END$$

DELIMITER ;

-- Vérification rapide :
--   SELECT Id_Vente, Nom_Vendeur, Id_Vendeur FROM Vente; -- Id_Vendeur doit être rempli
--   SHOW TRIGGERS; -- doit lister les 6 triggers ci-dessus

-- ============================================================================
-- 2) Migration — index composite sur Notification_B2B
--
-- Contexte : le badge de notifications non lues (NotificationApiController,
-- interrogé à chaque chargement de page côté header) et la page Notifications
-- filtrent systématiquement sur (Id_Entreprise_Destinataire, Est_Lue). Seul
-- Id_Entreprise_Destinataire est indexé jusqu'ici ; MySQL doit donc balayer
-- toutes les notifications de l'entreprise pour compter les non lues.
-- ============================================================================

ALTER TABLE `Notification_B2B`
  ADD INDEX `idx_notif_dest_lue` (`Id_Entreprise_Destinataire`, `Est_Lue`);

-- ============================================================================
-- 3) Migration — index sur les codes-barres de Produit
--
-- Contexte : le lookup code-barre (scan à la vente, à l'approvisionnement,
-- au scanner mobile) filtre sur
--   Id_Entreprise = ? AND (Code_Barre_Unite = ? OR Code_Barre_Carton = ?)
-- Seul Id_Entreprise est indexé jusqu'ici. Un index sur chaque colonne de
-- code-barre (plutôt qu'un composite démarrant par Id_Entreprise, peu
-- sélectif ici) permet à MySQL de restreindre directement sur la valeur
-- scannée, qui est quasi unique par nature.
-- ============================================================================

ALTER TABLE `Produit`
  ADD INDEX `idx_produit_code_barre_unite` (`Code_Barre_Unite`),
  ADD INDEX `idx_produit_code_barre_carton` (`Code_Barre_Carton`);

-- ============================================================================
-- 4) Migration — localisation de livraison choisie par l'acheteur (B2B)
--
-- Contexte : Commande_B2B a déjà Adresse_Retrait pour le mode "retrait sur
-- place" (l'acheteur précise où il vient chercher la marchandise), mais rien
-- d'équivalent pour le mode "livraison" : l'adresse de livraison retombait
-- silencieusement sur l'adresse enregistrée de l'entreprise acheteuse
-- (Entreprise.Adresse_Entreprise), sans que l'acheteur ne puisse préciser un
-- point de livraison différent pour cette commande précise (un dépôt, un
-- chantier, une autre adresse que le siège).
--
-- Ajoute le symétrique de Adresse_Retrait pour le mode livraison, avec des
-- coordonnées (le texte seul ne suffit pas au livreur qui navigue via la
-- carte Leaflet/OSRM déjà en place).
-- ============================================================================

ALTER TABLE `Commande_B2B`
  ADD COLUMN `Adresse_Livraison` text DEFAULT NULL COMMENT 'Point de livraison choisi par l acheteur (mode livraison) ; NULL = adresse de l entreprise en secours' AFTER `Adresse_Retrait`,
  ADD COLUMN `Latitude_Livraison` decimal(10,7) DEFAULT NULL AFTER `Adresse_Livraison`,
  ADD COLUMN `Longitude_Livraison` decimal(10,7) DEFAULT NULL AFTER `Latitude_Livraison`;

-- ============================================================================
-- 5) Migration — Client et Ligne_Produit (catégories de produits)
--
-- Contexte : le MLD final du mémoire de soutenance (chapitre "Analyse Logique")
-- prévoit deux entités jamais implémentées jusqu'ici :
--   - Client(id_client, nom_client, telephone_client, email_client,
--     adresse_client, type_client, nif_client, date_creation, statut_client)
--   - Ligne_Produit(id_ligne_produit, libelle, id_entreprise#) + l'association
--     N:N Contenir(id_produit#, id_ligne_produit#) — RG1/RG2 : un produit peut
--     appartenir à plusieurs catégories, une catégorie regroupe plusieurs
--     produits, et est créée par une seule entreprise.
--
-- Un précédent audit (cf. migration 1 ci-dessus) avait volontairement laissé
-- cet écart de côté ("ajouter ces tables serait une vraie nouvelle
-- fonctionnalité, pas une correction silencieuse"). Cette migration l'assume
-- explicitement, sur demande, pour aligner l'application sur le mémoire.
--
-- Écarts assumés par rapport au MLD littéral :
--   - Client.Id_Entreprise est ajouté (absent du MLD) : toutes les autres
--     entités de l'app sont scopées par entreprise (isolation multi-tenant,
--     cf. README) ; un Client sans rattachement à une entreprise casserait
--     ce principe et empêcherait de lister "mes clients" en toute sécurité.
--   - Le lien Client<->Facture est porté par Facture.Id_Client (et non
--     Client.Id_Facture comme écrit dans le MLD) : c'est la seule direction
--     cohérente avec la RG13 du mémoire elle-même ("une facture concerne un
--     seul client ; un client peut recevoir plusieurs factures") — un FK
--     Client -> Facture limiterait au contraire chaque client à une seule
--     facture.
--   - Vente.Id_Client est ajouté en miroir de Vente.Id_Vendeur (déjà en
--     place) : Nom_Client reste le libellé de cache affiché partout,
--     Id_Client devient la source de vérité pour l'agrégation par client.
--
-- **Indispensable** si vous générez des ventes depuis le code applicatif :
-- sans cette étape, INSERT INTO Vente échoue avec
-- SQLSTATE[42S22]: Unknown column 'Id_Client'.
-- ============================================================================

-- 5.1) Client

CREATE TABLE IF NOT EXISTS `Client` (
  `Id_Client` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int NOT NULL COMMENT 'Écart assumé vs MLD : isolation multi-entreprise',
  `Nom_Client` varchar(100) NOT NULL,
  `Telephone_Client` varchar(20) DEFAULT NULL,
  `Email_Client` varchar(100) DEFAULT NULL,
  `Adresse_Client` varchar(200) DEFAULT NULL,
  `Type_Client` enum('direct','entreprise') NOT NULL DEFAULT 'direct',
  `NIF_Client` varchar(50) DEFAULT NULL,
  `Date_Creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `Statut_Client` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`Id_Client`),
  UNIQUE KEY `uniq_client_entreprise_nom` (`Id_Entreprise`, `Nom_Client`),
  CONSTRAINT `client_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Vente.Id_Client — miroir de Vente.Id_Vendeur (Nom_Client reste le cache d'affichage)
ALTER TABLE `Vente`
  ADD COLUMN `Id_Client` int DEFAULT NULL COMMENT 'FK Client ; Nom_Client reste un libellé de cache' AFTER `Nom_Client`,
  ADD KEY `idx_vente_client` (`Id_Client`),
  ADD CONSTRAINT `fk_vente_client` FOREIGN KEY (`Id_Client`) REFERENCES `Client` (`Id_Client`) ON DELETE SET NULL;

-- Facture.Id_Client — cf. note RG13 ci-dessus (sens inverse du MLD littéral)
ALTER TABLE `Facture`
  ADD COLUMN `Id_Client` int DEFAULT NULL AFTER `Id_Vente`,
  ADD KEY `idx_facture_client` (`Id_Client`),
  ADD CONSTRAINT `fk_facture_client` FOREIGN KEY (`Id_Client`) REFERENCES `Client` (`Id_Client`) ON DELETE SET NULL;

-- Backfill : un Client par (entreprise, nom) déjà présent dans l'historique des ventes.
INSERT INTO `Client` (`Id_Entreprise`, `Nom_Client`, `Type_Client`)
SELECT DISTINCT `Id_Entreprise`, `Nom_Client`, 'direct'
FROM `Vente`
WHERE `Nom_Client` IS NOT NULL AND `Nom_Client` <> '' AND `Id_Entreprise` IS NOT NULL
ON DUPLICATE KEY UPDATE `Nom_Client` = VALUES(`Nom_Client`);

UPDATE `Vente` v
JOIN `Client` c ON c.`Id_Entreprise` = v.`Id_Entreprise` AND c.`Nom_Client` = v.`Nom_Client`
SET v.`Id_Client` = c.`Id_Client`
WHERE v.`Id_Client` IS NULL;

UPDATE `Facture` f
JOIN `Vente` v ON v.`Id_Vente` = f.`Id_Vente`
SET f.`Id_Client` = v.`Id_Client`
WHERE f.`Id_Client` IS NULL AND v.`Id_Client` IS NOT NULL;

-- 5.2) Ligne_Produit + Contenir (catégories de produits, RG1/RG2)

CREATE TABLE IF NOT EXISTS `Ligne_Produit` (
  `Id_Ligne_Produit` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int NOT NULL,
  `Libelle` varchar(100) NOT NULL,
  PRIMARY KEY (`Id_Ligne_Produit`),
  UNIQUE KEY `uniq_ligneproduit_entreprise_libelle` (`Id_Entreprise`, `Libelle`),
  CONSTRAINT `ligne_produit_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `Contenir` (
  `Id_Produit` int NOT NULL,
  `Id_Ligne_Produit` int NOT NULL,
  PRIMARY KEY (`Id_Produit`, `Id_Ligne_Produit`),
  KEY `idx_contenir_ligne` (`Id_Ligne_Produit`),
  CONSTRAINT `contenir_ibfk_1` FOREIGN KEY (`Id_Produit`) REFERENCES `Produit` (`Id_Produit`) ON DELETE CASCADE,
  CONSTRAINT `contenir_ibfk_2` FOREIGN KEY (`Id_Ligne_Produit`) REFERENCES `Ligne_Produit` (`Id_Ligne_Produit`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Vérification rapide :
--   SELECT COUNT(*) FROM Client;             -- backfillé depuis Vente
--   SELECT Id_Vente, Nom_Client, Id_Client FROM Vente; -- Id_Client doit être rempli
--   SHOW TABLES LIKE 'Ligne_Produit'; SHOW TABLES LIKE 'Contenir';

-- ============================================================================
-- 6) Migration — Confirmation d'email à l'inscription
--
-- Contexte : jusqu'ici, un compte créé via pages/register.php était utilisable
-- immédiatement. Cette migration ajoute une étape de confirmation par email
-- (code à 6 chiffres) avant que le compte ne devienne utilisable pour se
-- connecter.
--
-- Non destructif : ADD COLUMN (DEFAULT 1, donc tous les comptes existants
-- restent utilisables sans action) + CREATE TABLE.
--
-- **Indispensable** : sans cette étape, pages/register.php échoue avec
-- SQLSTATE[42S22]: Unknown column 'Email_Verifie'.
-- ============================================================================

ALTER TABLE `Utilisateur`
  ADD COLUMN `Email_Verifie` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Comptes déjà existants = vérifiés ; nouvelles inscriptions démarrent à 0' AFTER `Email_Utilisateur`;

CREATE TABLE IF NOT EXISTS `Email_Confirmation` (
  `Id_Confirmation` int NOT NULL AUTO_INCREMENT,
  `Id_Utilisateur` int NOT NULL,
  `Code` varchar(6) NOT NULL,
  `Expire_At` datetime NOT NULL,
  `Utilise` tinyint(1) DEFAULT '0',
  `Created_At` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Confirmation`),
  KEY `Id_Utilisateur` (`Id_Utilisateur`),
  CONSTRAINT `email_confirmation_ibfk_1` FOREIGN KEY (`Id_Utilisateur`) REFERENCES `Utilisateur` (`Id_Utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- Fin des migrations. Ajouter toute nouvelle migration après cette ligne,
-- avec un bloc "-- N) Migration — <titre>" et une entrée dans le sommaire en
-- tête de fichier + dans README.md.
-- ============================================================================
