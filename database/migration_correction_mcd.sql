-- ============================================================================
-- Migration corrective — FactuPro
--
-- Contexte : le MCD fourni (image.png) a servi de base à une revue (dossier
-- « Dossier FactuPro »), mais la comparaison avec le schéma réel
-- (database/facturation.sql) montre que la base a déjà évolué bien au-delà
-- du diagramme :
--   - Utilisateur, Audit_Log, Historique_Commande_B2B, Logistique,
--     Password_Reset, Scan_Session(_Scan) existent en réalité et n'étaient
--     pas sur le diagramme.
--   - Client et Ligne_Produit n'ont jamais été implémentés : Vente utilise
--     un simple Nom_Client texte, et Produit n'a pas de catégorie — ce n'est
--     pas un oubli à corriger silencieusement, juste un écart diagramme/code
--     à noter (ajouter ces tables serait une vraie nouvelle fonctionnalité,
--     pas une correction).
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
-- Non destructif : uniquement des CREATE TABLE / ALTER TABLE ADD / CREATE
-- TRIGGER. Aucune donnée existante n'est supprimée ou modifiée hors des
-- colonnes ajoutées ici. Sûr à exécuter sur la base actuelle.
--
-- Suivi requis côté PHP (hors SQL, à faire séparément) :
--   - src/Infrastructure/Persistence/InvoiceRepository.php::createSale()
--     et src/Infrastructure/Persistence/OrderRepository.php::createB2BSale()
--     doivent désormais insérer aussi dans Ligne_Vente et renseigner
--     Id_Vendeur, sans quoi ces colonnes resteront vides pour les nouvelles
--     ventes.
--   - database/reset_demo.php : ajouter 'Ligne_Vente' à la liste $tables
--     (avant 'Vente', comme 'Ligne_Commande_B2B' est placé avant
--     'Commande_B2B'), sinon un reset laisse des lignes orphelines.
-- ============================================================================

USE `facturation`;

-- ----------------------------------------------------------------------------
-- 1) Ligne_Vente — miroir de Ligne_Commande_B2B, corrige l'absence de lignes
--    relationnelles pour les ventes comptoir.
-- ----------------------------------------------------------------------------

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
  COMMENT '[DEPRECATED] Remplacé par Ligne_Vente pour les nouvelles ventes ; conservé pour les ventes historiques et l'affichage.';

-- Pas de ré-import automatique de l'historique : le JSON des ventes 1-9 ne
-- porte pas toujours d'Id_Produit fiable (seulement un `nom`), un rapprochement
-- par nom serait risqué (produits renommés/doublons). Les nouvelles ventes
-- seules alimenteront Ligne_Vente une fois le code PHP mis à jour.

-- ----------------------------------------------------------------------------
-- 2) Vente.Id_Vendeur — rattache enfin le vendeur à un compte Utilisateur.
--    Nom_Vendeur est conservé comme libellé d'affichage en cache.
-- ----------------------------------------------------------------------------

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

-- ----------------------------------------------------------------------------
-- 3) Montant_Total auto-maintenu par trigger — pour Vente et Commande_B2B,
--    évite toute dérive entre le total stocké et la somme réelle des lignes.
-- ----------------------------------------------------------------------------

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

-- ----------------------------------------------------------------------------
-- Vérification rapide après exécution :
--   SELECT Id_Vente, Nom_Vendeur, Id_Vendeur FROM Vente; -- Id_Vendeur doit être rempli
--   SHOW TRIGGERS; -- doit lister les 6 triggers ci-dessus
-- ----------------------------------------------------------------------------
