-- ============================================================================
-- FactuPro — Base de données complète (fichier unique)
--
-- Schéma complet de la base `facturation` : 21 tables, index, clés étrangères
-- et triggers (recalcul de Montant_Total sur Vente / Commande_B2B).
-- Aucune donnée : la base démarre vide, le premier compte se crée depuis la
-- page d'inscription.
--
-- ATTENTION : ce fichier crée la base `facturation` et contient des
-- DROP TABLE IF EXISTS — l'importer sur une base existante EFFACE toutes ses
-- données. À n'utiliser que pour une installation neuve.
--
-- Import : mysql -u root --default-character-set=utf8mb4 < database/facturation.sql
-- Docker : chargé automatiquement au premier démarrage du conteneur MySQL.
-- ============================================================================

-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: facturation
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `facturation`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `facturation` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `facturation`;

--
-- Table structure for table `Annonce`
--

DROP TABLE IF EXISTS `Annonce`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Annonce` (
  `Id_Annonce` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int DEFAULT NULL,
  `Type_Annonce` enum('appel_offre','partenariat') NOT NULL,
  `Titre` varchar(200) NOT NULL,
  `Description` text,
  `Date_Publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `Statut` enum('active','expiree','terminee') DEFAULT 'active',
  PRIMARY KEY (`Id_Annonce`),
  KEY `Id_Entreprise` (`Id_Entreprise`),
  CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Audit_Log`
--

DROP TABLE IF EXISTS `Audit_Log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Audit_Log` (
  `Id_Log` int unsigned NOT NULL AUTO_INCREMENT,
  `Id_Utilisateur` int unsigned NOT NULL,
  `Id_Entreprise` int unsigned NOT NULL,
  `Action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Table_Cible` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Id_Cible` int unsigned DEFAULT NULL,
  `Details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `IP_Address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Created_At` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Log`),
  KEY `idx_log_user` (`Id_Utilisateur`),
  KEY `idx_log_entreprise` (`Id_Entreprise`),
  KEY `idx_log_created` (`Created_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Chat_B2B`
--

DROP TABLE IF EXISTS `Chat_B2B`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Chat_B2B` (
  `Id_Message` int NOT NULL AUTO_INCREMENT,
  `Id_Commande_B2B` int NOT NULL,
  `Id_Entreprise_Emetteur` int NOT NULL,
  `Message` text,
  `Type_Message` enum('texte','negociation_qte','negociation_delai','confirmation_dispo','fichier') DEFAULT 'texte',
  `Fichier_Path` varchar(500) DEFAULT NULL,
  `Fichier_Nom` varchar(255) DEFAULT NULL,
  `Est_Lu_Acheteur` tinyint(1) DEFAULT '0',
  `Est_Lu_Vendeur` tinyint(1) DEFAULT '0',
  `Date_Envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Message`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `Id_Entreprise_Emetteur` (`Id_Entreprise_Emetteur`),
  CONSTRAINT `chat_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `chat_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Emetteur`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Client`
--

DROP TABLE IF EXISTS `Client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Client` (
  `Id_Client` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int NOT NULL COMMENT 'Ã‰cart assumÃ© vs MLD : isolation multi-entreprise',
  `Nom_Client` varchar(100) NOT NULL,
  `Telephone_Client` varchar(20) DEFAULT NULL,
  `Email_Client` varchar(100) DEFAULT NULL,
  `Adresse_Client` varchar(200) DEFAULT NULL,
  `Type_Client` enum('direct','entreprise') NOT NULL DEFAULT 'direct',
  `NIF_Client` varchar(50) DEFAULT NULL,
  `Date_Creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `Statut_Client` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`Id_Client`),
  UNIQUE KEY `uniq_client_entreprise_nom` (`Id_Entreprise`,`Nom_Client`),
  CONSTRAINT `client_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Commande_B2B`
--

DROP TABLE IF EXISTS `Commande_B2B`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Commande_B2B` (
  `Id_Commande_B2B` int NOT NULL AUTO_INCREMENT,
  `Numero_Commande` varchar(50) NOT NULL,
  `Id_Entreprise_Acheteuse` int DEFAULT NULL,
  `Id_Entreprise_Vendeuse` int DEFAULT NULL,
  `Articles_JSON` text COMMENT '[DEPRECATED] Conservé pour rétrocompatibilité',
  `Montant_Total` decimal(10,2) NOT NULL,
  `Date_Commande` datetime DEFAULT CURRENT_TIMESTAMP,
  `Statut` enum('en_attente','validee','en_preparation','prete','expediee','livree','refusee') DEFAULT 'en_attente',
  `Est_Urgente` tinyint(1) DEFAULT '0',
  `Delai_Reponse_Minutes` int DEFAULT '120',
  `Date_Limite_Reponse` datetime DEFAULT NULL,
  `Mode_Retrait` enum('livraison','retrait_place') DEFAULT 'livraison',
  `Adresse_Retrait` text,
  `Adresse_Livraison` text COMMENT 'Point de livraison choisi par l acheteur (mode livraison) ; NULL = adresse de l entreprise en secours',
  `Latitude_Livraison` decimal(10,7) DEFAULT NULL,
  `Longitude_Livraison` decimal(10,7) DEFAULT NULL,
  `Date_Expedition_Reelle` datetime DEFAULT NULL,
  `Message_Validation` text,
  `Date_Validation` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Commande_B2B`),
  UNIQUE KEY `Numero_Commande` (`Numero_Commande`),
  KEY `Id_Entreprise_Acheteuse` (`Id_Entreprise_Acheteuse`),
  KEY `idx_cmd_b2b_vendeuse_statut` (`Id_Entreprise_Vendeuse`,`Statut`),
  CONSTRAINT `commande_b2b_ibfk_1` FOREIGN KEY (`Id_Entreprise_Acheteuse`) REFERENCES `Entreprise` (`Id_Entreprise`),
  CONSTRAINT `commande_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Vendeuse`) REFERENCES `Entreprise` (`Id_Entreprise`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Contenir`
--

DROP TABLE IF EXISTS `Contenir`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Contenir` (
  `Id_Produit` int NOT NULL,
  `Id_Ligne_Produit` int NOT NULL,
  PRIMARY KEY (`Id_Produit`,`Id_Ligne_Produit`),
  KEY `idx_contenir_ligne` (`Id_Ligne_Produit`),
  CONSTRAINT `contenir_ibfk_1` FOREIGN KEY (`Id_Produit`) REFERENCES `Produit` (`Id_Produit`) ON DELETE CASCADE,
  CONSTRAINT `contenir_ibfk_2` FOREIGN KEY (`Id_Ligne_Produit`) REFERENCES `Ligne_Produit` (`Id_Ligne_Produit`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Email_Confirmation`
--

DROP TABLE IF EXISTS `Email_Confirmation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Email_Confirmation` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Entreprise`
--

DROP TABLE IF EXISTS `Entreprise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Entreprise` (
  `Id_Entreprise` int NOT NULL AUTO_INCREMENT,
  `Nom_Entreprise` varchar(100) NOT NULL,
  `Adresse_Entreprise` varchar(200) DEFAULT NULL,
  `Tel_Entreprise` varchar(20) DEFAULT NULL,
  `Email_Entreprise` varchar(100) DEFAULT NULL,
  `NIF_Entreprise` varchar(50) DEFAULT NULL,
  `Secteur_Activite` varchar(100) DEFAULT NULL,
  `Description_Entreprise` text,
  `Score_Fiabilite` int DEFAULT '100',
  `Nombre_Commandes_Completees` int DEFAULT '0',
  `Latitude` decimal(10,7) DEFAULT NULL,
  `Longitude` decimal(10,7) DEFAULT NULL,
  `Ville` varchar(100) DEFAULT NULL,
  `Region` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`Id_Entreprise`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Facture`
--

DROP TABLE IF EXISTS `Facture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Facture` (
  `Id_Facture` int NOT NULL AUTO_INCREMENT,
  `Id_Vente` int DEFAULT NULL,
  `Id_Client` int DEFAULT NULL,
  `Id_Commande_B2B` int DEFAULT NULL,
  `Numero_Facture` varchar(50) NOT NULL,
  `Date_Facture` datetime DEFAULT CURRENT_TIMESTAMP,
  `Date_Echeance` datetime DEFAULT NULL,
  `Statut_Paiement` enum('non_payee','payee','en_retard','annulee') DEFAULT 'non_payee',
  `Montant_HT` decimal(10,2) DEFAULT NULL,
  `TVA` decimal(10,2) DEFAULT '0.00',
  `Montant_TTC` decimal(10,2) NOT NULL,
  `Id_Entreprise` int DEFAULT NULL,
  `Date_Archivage` datetime NOT NULL COMMENT 'Date limite légale de conservation = Date_Facture + 10 ans. Obligatoire.',
  PRIMARY KEY (`Id_Facture`),
  UNIQUE KEY `Numero_Facture` (`Numero_Facture`),
  KEY `Id_Vente` (`Id_Vente`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `idx_facture_date` (`Date_Facture`),
  KEY `idx_facture_archivage` (`Date_Archivage`),
  KEY `idx_facture_ent_statut` (`Id_Entreprise`,`Statut_Paiement`),
  KEY `idx_facture_client` (`Id_Client`),
  CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `Vente` (`Id_Vente`) ON DELETE SET NULL,
  CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE SET NULL,
  CONSTRAINT `fk_facture_client` FOREIGN KEY (`Id_Client`) REFERENCES `Client` (`Id_Client`) ON DELETE SET NULL,
  CONSTRAINT `fk_facture_entreprise` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Historique_Commande_B2B`
--

DROP TABLE IF EXISTS `Historique_Commande_B2B`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Historique_Commande_B2B` (
  `Id_Historique` int NOT NULL AUTO_INCREMENT,
  `Id_Commande_B2B` int NOT NULL,
  `Ancien_Statut` varchar(30) DEFAULT NULL,
  `Nouveau_Statut` varchar(30) NOT NULL,
  `Note` text,
  `Id_Entreprise_Action` int DEFAULT NULL,
  `Date_Changement` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Historique`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `Id_Entreprise_Action` (`Id_Entreprise_Action`),
  CONSTRAINT `historique_commande_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `historique_commande_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Action`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Ligne_Commande_B2B`
--

DROP TABLE IF EXISTS `Ligne_Commande_B2B`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Ligne_Commande_B2B` (
  `Id_Ligne` int NOT NULL AUTO_INCREMENT,
  `Id_Commande_B2B` int NOT NULL,
  `Id_Produit` int NOT NULL,
  `Nom_Produit` varchar(200) NOT NULL,
  `Quantite` int NOT NULL,
  `Quantite_Receptionnee` int NOT NULL DEFAULT '0',
  `Prix_Unitaire` decimal(10,2) NOT NULL,
  `Sous_Total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`Id_Ligne`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `Id_Produit` (`Id_Produit`),
  CONSTRAINT `ligne_commande_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `ligne_commande_b2b_ibfk_2` FOREIGN KEY (`Id_Produit`) REFERENCES `Produit` (`Id_Produit`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_cmd_b2b_ins` AFTER INSERT ON `Ligne_Commande_B2B` FOR EACH ROW BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_cmd_b2b_upd` AFTER UPDATE ON `Ligne_Commande_B2B` FOR EACH ROW BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = NEW.`Id_Commande_B2B`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_cmd_b2b_del` AFTER DELETE ON `Ligne_Commande_B2B` FOR EACH ROW BEGIN
  UPDATE `Commande_B2B` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Commande_B2B` WHERE `Id_Commande_B2B` = OLD.`Id_Commande_B2B`
  ) WHERE `Id_Commande_B2B` = OLD.`Id_Commande_B2B`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `Ligne_Produit`
--

DROP TABLE IF EXISTS `Ligne_Produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Ligne_Produit` (
  `Id_Ligne_Produit` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int NOT NULL,
  `Libelle` varchar(100) NOT NULL,
  PRIMARY KEY (`Id_Ligne_Produit`),
  UNIQUE KEY `uniq_ligneproduit_entreprise_libelle` (`Id_Entreprise`,`Libelle`),
  CONSTRAINT `ligne_produit_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Ligne_Vente`
--

DROP TABLE IF EXISTS `Ligne_Vente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Ligne_Vente` (
  `Id_Ligne_Vente` int NOT NULL AUTO_INCREMENT,
  `Id_Vente` int NOT NULL,
  `Id_Produit` int NOT NULL,
  `Nom_Produit` varchar(200) NOT NULL COMMENT 'Historisé : copie du nom au moment de la vente',
  `Quantite` int NOT NULL,
  `Prix_Unitaire` decimal(10,2) NOT NULL COMMENT 'Historisé au moment de la vente',
  `Sous_Total` decimal(10,2) GENERATED ALWAYS AS ((`Quantite` * `Prix_Unitaire`)) STORED,
  PRIMARY KEY (`Id_Ligne_Vente`),
  KEY `Id_Vente` (`Id_Vente`),
  KEY `Id_Produit` (`Id_Produit`),
  CONSTRAINT `ligne_vente_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `Vente` (`Id_Vente`) ON DELETE CASCADE,
  CONSTRAINT `ligne_vente_ibfk_2` FOREIGN KEY (`Id_Produit`) REFERENCES `Produit` (`Id_Produit`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_vente_ins` AFTER INSERT ON `Ligne_Vente` FOR EACH ROW BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = NEW.`Id_Vente`
  ) WHERE `Id_Vente` = NEW.`Id_Vente`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_vente_upd` AFTER UPDATE ON `Ligne_Vente` FOR EACH ROW BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = NEW.`Id_Vente`
  ) WHERE `Id_Vente` = NEW.`Id_Vente`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_ligne_vente_del` AFTER DELETE ON `Ligne_Vente` FOR EACH ROW BEGIN
  UPDATE `Vente` SET `Montant_Total` = (
    SELECT COALESCE(SUM(`Sous_Total`), 0) FROM `Ligne_Vente` WHERE `Id_Vente` = OLD.`Id_Vente`
  ) WHERE `Id_Vente` = OLD.`Id_Vente`;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `Logistique`
--

DROP TABLE IF EXISTS `Logistique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Logistique` (
  `Id_Logistique` int NOT NULL AUTO_INCREMENT,
  `Id_Vente` int DEFAULT NULL,
  `Id_Commande_B2B` int DEFAULT NULL,
  `Id_Facture` int DEFAULT NULL,
  `Transporteur` varchar(100) DEFAULT NULL,
  `Numero_Suivi` varchar(100) DEFAULT NULL,
  `Statut_Livraison` enum('traitement','en_attente','expediee','livree','annulee') DEFAULT 'traitement',
  `Date_Expedition` datetime DEFAULT NULL,
  `Date_Livraison_Prevue` datetime DEFAULT NULL,
  `Date_Livraison_Effectuee` datetime DEFAULT NULL,
  `Adresse_Livraison` text,
  `Notes_Logistique` text,
  `Id_Entreprise` int DEFAULT NULL,
  `Adresse_Livraison_Lat` decimal(10,7) DEFAULT NULL,
  `Adresse_Livraison_Lng` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`Id_Logistique`),
  KEY `Id_Vente` (`Id_Vente`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `Id_Facture` (`Id_Facture`),
  KEY `idx_logistique_ent_statut` (`Id_Entreprise`,`Statut_Livraison`),
  CONSTRAINT `logistique_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `Vente` (`Id_Vente`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_3` FOREIGN KEY (`Id_Facture`) REFERENCES `Facture` (`Id_Facture`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_4` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Notification_B2B`
--

DROP TABLE IF EXISTS `Notification_B2B`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Notification_B2B` (
  `Id_Notification` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise_Destinataire` int NOT NULL,
  `Type_Notif` enum('nouvelle_commande','commande_urgente','nouveau_message','validation','refus','preparation','prete','livraison','expedition','reception') NOT NULL,
  `Titre` varchar(200) NOT NULL,
  `Message` text,
  `Id_Commande_B2B` int DEFAULT NULL,
  `Est_Lue` tinyint(1) DEFAULT '0',
  `Date_Creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Notification`),
  KEY `Id_Entreprise_Destinataire` (`Id_Entreprise_Destinataire`),
  KEY `Id_Commande_B2B` (`Id_Commande_B2B`),
  KEY `idx_notif_dest_lue` (`Id_Entreprise_Destinataire`,`Est_Lue`),
  CONSTRAINT `notification_b2b_ibfk_1` FOREIGN KEY (`Id_Entreprise_Destinataire`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE,
  CONSTRAINT `notification_b2b_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `Commande_B2B` (`Id_Commande_B2B`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Password_Reset`
--

DROP TABLE IF EXISTS `Password_Reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Password_Reset` (
  `Id_Reset` int NOT NULL AUTO_INCREMENT,
  `Id_Utilisateur` int NOT NULL,
  `Token` varchar(64) NOT NULL,
  `Expire_At` datetime NOT NULL,
  `Utilise` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`Id_Reset`),
  UNIQUE KEY `Token` (`Token`),
  KEY `Id_Utilisateur` (`Id_Utilisateur`),
  CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`Id_Utilisateur`) REFERENCES `Utilisateur` (`Id_Utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Produit`
--

DROP TABLE IF EXISTS `Produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Produit` (
  `Id_Produit` int NOT NULL AUTO_INCREMENT,
  `Nom_Produit` varchar(100) NOT NULL,
  `Description_Produit` text,
  `Prix_Unitaire_Produit` decimal(10,2) NOT NULL,
  `Quantite_En_Stock` int DEFAULT '0',
  `Code_Barre_Unite` varchar(100) DEFAULT NULL,
  `Code_Barre_Carton` varchar(100) DEFAULT NULL,
  `Quantite_Par_Carton` int DEFAULT '1',
  `En_Destockage_B2B` tinyint(1) DEFAULT '0',
  `Prix_B2B` decimal(10,2) DEFAULT NULL,
  `Quantite_Min_B2B` int DEFAULT '1',
  `Id_Entreprise` int DEFAULT NULL,
  `Seuil_Alerte_Stock` int unsigned NOT NULL DEFAULT '5' COMMENT 'Alerte si Quantite_En_Stock <= seuil',
  PRIMARY KEY (`Id_Produit`),
  KEY `idx_produit_ent` (`Id_Entreprise`),
  KEY `idx_produit_code_barre_unite` (`Code_Barre_Unite`),
  KEY `idx_produit_code_barre_carton` (`Code_Barre_Carton`),
  CONSTRAINT `produit_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Scan_Session`
--

DROP TABLE IF EXISTS `Scan_Session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Scan_Session` (
  `Id_Scan_Session` int NOT NULL AUTO_INCREMENT,
  `Token` varchar(64) NOT NULL COMMENT 'bin2hex(random_bytes(32)) â€” credential du telephone, encode dans le QR',
  `Id_Utilisateur` int NOT NULL COMMENT 'Proprietaire PC de la session',
  `Id_Entreprise` int NOT NULL,
  `Statut` enum('en_attente','connecte','expire','revoque') NOT NULL DEFAULT 'en_attente',
  `Mode` enum('produit','texte') NOT NULL DEFAULT 'produit',
  `Phone_Device_Token` varchar(64) DEFAULT NULL COMMENT 'Attribue au 1er telephone qui rejoint, empeche un second appareil de reprendre le token',
  `Created_At` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Expires_At` datetime NOT NULL,
  `Last_Activity` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Scan_Session`),
  UNIQUE KEY `idx_scan_session_token` (`Token`),
  KEY `idx_scan_session_utilisateur` (`Id_Utilisateur`),
  KEY `idx_scan_session_entreprise` (`Id_Entreprise`),
  KEY `idx_scan_session_expires` (`Expires_At`),
  CONSTRAINT `fk_scan_session_entreprise` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE,
  CONSTRAINT `fk_scan_session_utilisateur` FOREIGN KEY (`Id_Utilisateur`) REFERENCES `Utilisateur` (`Id_Utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Scan_Session_Scan`
--

DROP TABLE IF EXISTS `Scan_Session_Scan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Scan_Session_Scan` (
  `Id_Scan` int NOT NULL AUTO_INCREMENT,
  `Id_Scan_Session` int NOT NULL,
  `Code_Barre` varchar(100) NOT NULL,
  `Id_Produit` int DEFAULT NULL COMMENT 'Produit resolu cote serveur au moment du scan, pour audit',
  `Created_At` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_Scan`),
  KEY `idx_scan_session_scan_session` (`Id_Scan_Session`,`Id_Scan`),
  CONSTRAINT `fk_scan_session_scan_session` FOREIGN KEY (`Id_Scan_Session`) REFERENCES `Scan_Session` (`Id_Scan_Session`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Utilisateur`
--

DROP TABLE IF EXISTS `Utilisateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Utilisateur` (
  `Id_Utilisateur` int NOT NULL AUTO_INCREMENT,
  `Nom_Utilisateur` varchar(50) NOT NULL,
  `Email_Utilisateur` varchar(100) NOT NULL,
  `Email_Verifie` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Comptes dÃ©jÃ  existants = vÃ©rifiÃ©s ; nouvelles inscriptions dÃ©marrent Ã  0',
  `Mot_De_Passe_Utilisateur` varchar(255) NOT NULL,
  `Role_Utilisateur` enum('admin','proprio','vendeur','livreur') NOT NULL DEFAULT 'proprio',
  `Id_Entreprise` int DEFAULT NULL,
  PRIMARY KEY (`Id_Utilisateur`),
  UNIQUE KEY `Nom_Utilisateur` (`Nom_Utilisateur`),
  UNIQUE KEY `Email_Utilisateur` (`Email_Utilisateur`),
  KEY `Id_Entreprise` (`Id_Entreprise`),
  CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Vente`
--

DROP TABLE IF EXISTS `Vente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Vente` (
  `Id_Vente` int NOT NULL AUTO_INCREMENT,
  `Numero_Vente` varchar(50) NOT NULL,
  `Nom_Client` varchar(100) DEFAULT 'Client Comptant',
  `Id_Client` int DEFAULT NULL COMMENT 'FK Client ; Nom_Client reste un libellÃ© de cache',
  `Nom_Vendeur` varchar(100) DEFAULT NULL,
  `Id_Vendeur` int DEFAULT NULL COMMENT 'FK Utilisateur ; Nom_Vendeur reste un libellÃ© de cache',
  `Date_Vente` datetime DEFAULT CURRENT_TIMESTAMP,
  `Articles_JSON` text COMMENT '[DEPRECATED] RemplacÃ© par Ligne_Vente pour les nouvelles ventes ; conservÃ© pour les ventes historiques et l''affichage.',
  `Montant_Total` decimal(10,2) NOT NULL,
  `Type_Vente` enum('directe','b2b') DEFAULT 'directe',
  `Id_Entreprise` int DEFAULT NULL,
  PRIMARY KEY (`Id_Vente`),
  UNIQUE KEY `Numero_Vente` (`Numero_Vente`),
  KEY `idx_vente_ent_date` (`Id_Entreprise`,`Date_Vente`),
  KEY `idx_vente_vendeur` (`Id_Vendeur`),
  KEY `idx_vente_client` (`Id_Client`),
  CONSTRAINT `fk_vente_client` FOREIGN KEY (`Id_Client`) REFERENCES `Client` (`Id_Client`) ON DELETE SET NULL,
  CONSTRAINT `fk_vente_vendeur` FOREIGN KEY (`Id_Vendeur`) REFERENCES `Utilisateur` (`Id_Utilisateur`) ON DELETE SET NULL,
  CONSTRAINT `vente_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `Entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'facturation'
--

--
-- Dumping routines for database 'facturation'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
