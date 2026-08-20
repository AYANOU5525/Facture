-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: facturation
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `annonce`
--

DROP TABLE IF EXISTS `annonce`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annonce` (
  `Id_Annonce` int NOT NULL AUTO_INCREMENT,
  `Id_Entreprise` int DEFAULT NULL,
  `Type_Annonce` enum('appel_offre','partenariat') NOT NULL,
  `Titre` varchar(200) NOT NULL,
  `Description` text,
  `Date_Publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `Statut` enum('active','expiree','terminee') DEFAULT 'active',
  PRIMARY KEY (`Id_Annonce`),
  KEY `Id_Entreprise` (`Id_Entreprise`),
  CONSTRAINT `annonce_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annonce`
--

LOCK TABLES `annonce` WRITE;
/*!40000 ALTER TABLE `annonce` DISABLE KEYS */;
INSERT INTO `annonce` VALUES (1,1,'appel_offre','Recherche fournisseur accessoires PC — 500 unités/mois','TechVision Sarl recherche un fournisseur régulier pour des accessoires informatiques (câbles, hubs, adaptateurs). Volume mensuel estimé : 500 unités. Délai de réponse : 15 jours.','2026-08-10 08:00:00','active'),(2,2,'partenariat','Partenariat distribution produits alimentaires — Région Maritime','FourniBien SA propose un partenariat de distribution exclusive dans la région Maritime pour ses produits phares (riz, huile, sucre, farine). Nous cherchons des distributeurs fiables avec un réseau établi.','2026-08-05 10:00:00','active'),(3,1,'partenariat','Offre équipement bureautique — PME et administrations','TechVision Sarl propose des offres groupées d\'équipement bureautique pour les PME et organismes publics : écrans, claviers, souris, câbles. Remise à partir de 10 postes.','2026-08-14 09:30:00','active');
/*!40000 ALTER TABLE `annonce` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
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
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_b2b`
--

DROP TABLE IF EXISTS `chat_b2b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_b2b` (
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
  CONSTRAINT `chat_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `chat_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Emetteur`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_b2b`
--

LOCK TABLES `chat_b2b` WRITE;
/*!40000 ALTER TABLE `chat_b2b` DISABLE KEYS */;
INSERT INTO `chat_b2b` VALUES (1,1,2,'Bonjour, pouvez-vous confirmer la disponibilité des 10 souris ?','texte',NULL,NULL,1,1,'2026-07-30 08:30:00'),(2,1,1,'Oui, tout est disponible en stock. Livraison sous 48h confirmée.','texte',NULL,NULL,1,1,'2026-07-30 09:00:00'),(3,1,2,'Parfait, merci. On attend la livraison.','texte',NULL,NULL,1,1,'2026-07-30 09:10:00'),(4,3,1,'Commande urgente — besoin du riz et de l\'huile avant vendredi impérativement.','texte',NULL,NULL,1,1,'2026-08-10 10:10:00'),(5,3,2,'Reçu. Stock disponible. Nous expédions dès demain matin.','texte',NULL,NULL,1,1,'2026-08-10 11:00:00'),(6,3,1,'Super, merci beaucoup !','texte',NULL,NULL,0,1,'2026-08-10 11:05:00');
/*!40000 ALTER TABLE `chat_b2b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commande_b2b`
--

DROP TABLE IF EXISTS `commande_b2b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commande_b2b` (
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
  `Date_Expedition_Reelle` datetime DEFAULT NULL,
  `Message_Validation` text,
  `Date_Validation` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Commande_B2B`),
  UNIQUE KEY `Numero_Commande` (`Numero_Commande`),
  KEY `Id_Entreprise_Acheteuse` (`Id_Entreprise_Acheteuse`),
  KEY `idx_cmd_b2b_vendeuse_statut` (`Id_Entreprise_Vendeuse`,`Statut`),
  CONSTRAINT `commande_b2b_ibfk_1` FOREIGN KEY (`Id_Entreprise_Acheteuse`) REFERENCES `entreprise` (`Id_Entreprise`),
  CONSTRAINT `commande_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Vendeuse`) REFERENCES `entreprise` (`Id_Entreprise`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_b2b`
--

LOCK TABLES `commande_b2b` WRITE;
/*!40000 ALTER TABLE `commande_b2b` DISABLE KEYS */;
INSERT INTO `commande_b2b` VALUES (1,'CMD-B2B-20260730-001',2,1,NULL,265000.00,'2026-07-30 08:00:00','livree',0,120,NULL,'livraison',NULL,'2026-08-02 09:00:00','Commande validée, livraison prévue sous 48h.','2026-07-31 10:00:00'),(2,'CMD-B2B-20260815-002',2,1,NULL,170000.00,'2026-08-15 14:00:00','en_preparation',0,120,NULL,'livraison',NULL,NULL,'En cours de préparation, expédition prévue d\'ici 2 jours.','2026-08-16 09:30:00'),(3,'CMD-B2B-20260810-003',1,2,NULL,278000.00,'2026-08-10 10:00:00','expediee',1,120,NULL,'livraison',NULL,'2026-08-13 07:30:00','Commande urgente validée. Expédition effectuée le 13/08.','2026-08-11 08:00:00');
/*!40000 ALTER TABLE `commande_b2b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entreprise`
--

DROP TABLE IF EXISTS `entreprise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entreprise` (
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entreprise`
--

LOCK TABLES `entreprise` WRITE;
/*!40000 ALTER TABLE `entreprise` DISABLE KEYS */;
INSERT INTO `entreprise` VALUES (1,'TechVision Sarl','24 Avenue de la Libération, Lomé','+228 90 11 22 33','contact@techvision.tg','NIF-TG-2021-00142','Informatique & Électronique','Vente et distribution de matériel informatique et électronique grand public.',98,12,6.1722000,1.2313000,'Lomé','Maritime'),(2,'FourniBien SA','8 Rue du Commerce, Lomé','+228 91 44 55 66','info@fournibien.tg','NIF-TG-2019-00087','Agroalimentaire & Négoce','Grossiste en produits alimentaires et de grande consommation.',95,18,6.1400000,1.2200000,'Lomé','Maritime');
/*!40000 ALTER TABLE `entreprise` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facture`
--

DROP TABLE IF EXISTS `facture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facture` (
  `Id_Facture` int NOT NULL AUTO_INCREMENT,
  `Id_Vente` int DEFAULT NULL,
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
  CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `vente` (`Id_Vente`) ON DELETE SET NULL,
  CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE SET NULL,
  CONSTRAINT `fk_facture_entreprise` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facture`
--

LOCK TABLES `facture` WRITE;
/*!40000 ALTER TABLE `facture` DISABLE KEYS */;
INSERT INTO `facture` VALUES (1,1,NULL,'FAC-2026-0001','2026-08-01 09:15:00','2026-08-31 23:59:59','payee',123500.00,0.00,123500.00,1,'2036-08-01 00:00:00'),(2,2,NULL,'FAC-2026-0002','2026-08-05 11:30:00','2026-09-04 23:59:59','payee',29000.00,0.00,29000.00,1,'2036-08-05 00:00:00'),(3,3,NULL,'FAC-2026-0003','2026-08-08 14:00:00','2026-09-07 23:59:59','non_payee',22400.00,0.00,22400.00,1,'2036-08-08 00:00:00'),(4,4,NULL,'FAC-2026-0004','2026-08-12 10:45:00','2026-09-11 23:59:59','payee',109500.00,0.00,109500.00,1,'2036-08-12 00:00:00'),(5,5,NULL,'FAC-2026-0005','2026-08-18 16:20:00','2026-09-17 23:59:59','non_payee',36900.00,0.00,36900.00,1,'2036-08-18 00:00:00'),(6,NULL,1,'FAC-2026-B001','2026-08-02 09:00:00','2026-09-01 23:59:59','payee',265000.00,0.00,265000.00,1,'2036-08-02 00:00:00'),(7,NULL,3,'FAC-2026-B002','2026-08-13 07:30:00','2026-09-12 23:59:59','non_payee',278000.00,0.00,278000.00,2,'2036-08-13 00:00:00');
/*!40000 ALTER TABLE `facture` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historique_commande_b2b`
--

DROP TABLE IF EXISTS `historique_commande_b2b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historique_commande_b2b` (
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
  CONSTRAINT `historique_commande_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `historique_commande_b2b_ibfk_2` FOREIGN KEY (`Id_Entreprise_Action`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historique_commande_b2b`
--

LOCK TABLES `historique_commande_b2b` WRITE;
/*!40000 ALTER TABLE `historique_commande_b2b` DISABLE KEYS */;
/*!40000 ALTER TABLE `historique_commande_b2b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ligne_commande_b2b`
--

DROP TABLE IF EXISTS `ligne_commande_b2b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ligne_commande_b2b` (
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
  CONSTRAINT `ligne_commande_b2b_ibfk_1` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE CASCADE,
  CONSTRAINT `ligne_commande_b2b_ibfk_2` FOREIGN KEY (`Id_Produit`) REFERENCES `produit` (`Id_Produit`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ligne_commande_b2b`
--

LOCK TABLES `ligne_commande_b2b` WRITE;
/*!40000 ALTER TABLE `ligne_commande_b2b` DISABLE KEYS */;
INSERT INTO `ligne_commande_b2b` VALUES (1,1,2,'Clavier mécanique RGB',5,5,24000.00,120000.00),(2,1,3,'Souris sans fil',10,10,12000.00,120000.00),(3,1,4,'Câble HDMI 2m',6,6,4200.00,25000.00),(4,2,1,'Écran PC 27\" FHD',2,0,85000.00,170000.00),(5,3,6,'Riz long grain 25 kg',10,0,17000.00,170000.00),(6,3,7,'Huile de palme 5 L',20,0,5400.00,108000.00);
/*!40000 ALTER TABLE `ligne_commande_b2b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistique`
--

DROP TABLE IF EXISTS `logistique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistique` (
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
  CONSTRAINT `logistique_ibfk_1` FOREIGN KEY (`Id_Vente`) REFERENCES `vente` (`Id_Vente`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_3` FOREIGN KEY (`Id_Facture`) REFERENCES `facture` (`Id_Facture`) ON DELETE SET NULL,
  CONSTRAINT `logistique_ibfk_4` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistique`
--

LOCK TABLES `logistique` WRITE;
/*!40000 ALTER TABLE `logistique` DISABLE KEYS */;
INSERT INTO `logistique` VALUES (1,4,NULL,4,'Rapidex Express','RPX-20260812-4401','livree','2026-08-12 15:00:00','2026-08-13 12:00:00','2026-08-13 10:30:00','Quartier Bè, Rue des Palmiers, Lomé','Livraison effectuée sans incident. Colis remis en main propre.',1,6.1580000,1.2250000),(2,NULL,1,6,'TransLog Togo','TLT-20260802-0012','livree','2026-08-02 09:00:00','2026-08-04 17:00:00','2026-08-04 14:15:00','8 Rue du Commerce, Lomé','Livraison B2B — 16 colis au total, réception confirmée par Jean Fourni.',1,6.1400000,1.2200000),(3,NULL,3,7,'Sahel Transport','STR-20260813-0089','expediee','2026-08-13 07:30:00','2026-08-15 12:00:00',NULL,'24 Avenue de la Libération, Lomé','Commande urgente — 30 unités. Livraison attendue le 15/08.',2,6.1722000,1.2313000);
/*!40000 ALTER TABLE `logistique` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_b2b`
--

DROP TABLE IF EXISTS `notification_b2b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_b2b` (
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
  CONSTRAINT `notification_b2b_ibfk_1` FOREIGN KEY (`Id_Entreprise_Destinataire`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE,
  CONSTRAINT `notification_b2b_ibfk_2` FOREIGN KEY (`Id_Commande_B2B`) REFERENCES `commande_b2b` (`Id_Commande_B2B`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_b2b`
--

LOCK TABLES `notification_b2b` WRITE;
/*!40000 ALTER TABLE `notification_b2b` DISABLE KEYS */;
INSERT INTO `notification_b2b` VALUES (1,1,'nouvelle_commande','Nouvelle commande B2B reçue','FourniBien SA vient de passer une commande de 265 000 FCFA (CMD-B2B-20260730-001).',1,1,'2026-07-30 08:05:00'),(2,1,'nouvelle_commande','Nouvelle commande B2B reçue','FourniBien SA vient de passer une commande de 170 000 FCFA (CMD-B2B-20260815-002).',2,0,'2026-08-15 14:05:00'),(3,2,'expedition','Votre commande a été expédiée','TechVision Sarl a expédié votre commande CMD-B2B-20260730-001 via Rapidex Express.',1,1,'2026-08-02 09:10:00'),(4,2,'nouvelle_commande','Nouvelle commande B2B urgente','TechVision Sarl a passé une commande urgente de 278 000 FCFA (CMD-B2B-20260810-003).',3,0,'2026-08-10 10:05:00');
/*!40000 ALTER TABLE `notification_b2b` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset` (
  `Id_Reset` int NOT NULL AUTO_INCREMENT,
  `Id_Utilisateur` int NOT NULL,
  `Token` varchar(64) NOT NULL,
  `Expire_At` datetime NOT NULL,
  `Utilise` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`Id_Reset`),
  UNIQUE KEY `Token` (`Token`),
  KEY `Id_Utilisateur` (`Id_Utilisateur`),
  CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`Id_Utilisateur`) REFERENCES `utilisateur` (`Id_Utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset`
--

LOCK TABLES `password_reset` WRITE;
/*!40000 ALTER TABLE `password_reset` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produit`
--

DROP TABLE IF EXISTS `produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produit` (
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
  CONSTRAINT `produit_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produit`
--

LOCK TABLES `produit` WRITE;
/*!40000 ALTER TABLE `produit` DISABLE KEYS */;
INSERT INTO `produit` VALUES (1,'Écran PC 27\" FHD','Moniteur Full HD 1920×1080, dalle IPS, 75 Hz, entrées HDMI/VGA',95000.00,12,'3760001234001','3760001234100',4,1,85000.00,2,1,5),(2,'Clavier mécanique RGB','Switches blue, rétroéclairage RGB, disposition AZERTY',28500.00,25,'3760001234002','3760001234200',10,1,24000.00,5,1,5),(3,'Souris sans fil','Capteur 1600 DPI, autonomie 12 mois, nano-récepteur USB',14500.00,40,'3760001234003',NULL,1,1,12000.00,10,1,5),(4,'Câble HDMI 2m','HDMI 2.0, 4K 60 Hz, blindage triple, contacts dorés',4200.00,85,'3760001234004','3760001234400',20,0,NULL,1,1,5),(5,'Hub USB 4 ports','USB 3.0, transfert jusqu à 5 Gbps, compatible PC/Mac',9800.00,18,'3760001234005',NULL,1,0,NULL,1,1,5),(6,'Riz long grain 25 kg','Riz blanc étuvé, sac 25 kg, origine Thaïlande',19500.00,180,'6280001234006','6280001234600',5,1,17000.00,5,2,5),(7,'Huile de palme 5 L','Huile de palme raffinée, bidon 5 litres',6200.00,250,'6280001234007','6280001234700',12,1,5400.00,10,2,5),(8,'Sucre blanc 50 kg','Sucre cristallisé, sac 50 kg, origine locale',31000.00,120,'6280001234008','6280001234800',2,1,27500.00,3,2,5),(9,'Savon ménage (carton 24u)','Savon de lessive 400g, carton de 24 unités',10800.00,60,'6280001234009','6280001234900',1,1,9500.00,2,2,5),(10,'Farine de blé 25 kg','Farine T55 tout usage, sac 25 kg',13500.00,95,'6280001234010','6280001235000',4,1,11800.00,5,2,5);
/*!40000 ALTER TABLE `produit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `utilisateur` (
  `Id_Utilisateur` int NOT NULL AUTO_INCREMENT,
  `Nom_Utilisateur` varchar(50) NOT NULL,
  `Email_Utilisateur` varchar(100) NOT NULL,
  `Mot_De_Passe_Utilisateur` varchar(255) NOT NULL,
  `Role_Utilisateur` enum('admin','proprio','vendeur','livreur') NOT NULL DEFAULT 'proprio',
  `Id_Entreprise` int DEFAULT NULL,
  PRIMARY KEY (`Id_Utilisateur`),
  UNIQUE KEY `Nom_Utilisateur` (`Nom_Utilisateur`),
  UNIQUE KEY `Email_Utilisateur` (`Email_Utilisateur`),
  KEY `Id_Entreprise` (`Id_Entreprise`),
  CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateur`
--

LOCK TABLES `utilisateur` WRITE;
/*!40000 ALTER TABLE `utilisateur` DISABLE KEYS */;
INSERT INTO `utilisateur` VALUES
  (1,'alex_admin','alex@techvision.tg','$2y$10$r/z6iUXGII3XB4BunN8LxOFKBxuotlZSKpnSAE9WlyfZUPBkfCTvW','proprio',1),
  (2,'marie_tech','marie@techvision.tg','$2y$10$uFzgG715mE5d8ommB.7KJ.EWpLyMnCRrSUVQBAK5gxrpwgGDry5Aq','vendeur',1),
  (3,'fourni_admin','admin@fournibien.tg','$2y$10$r/z6iUXGII3XB4BunN8LxOFKBxuotlZSKpnSAE9WlyfZUPBkfCTvW','proprio',2),
  (4,'jean_fourni','jean@fournibien.tg','$2y$10$uFzgG715mE5d8ommB.7KJ.EWpLyMnCRrSUVQBAK5gxrpwgGDry5Aq','vendeur',2),
  (5,'superadmin','admin@factupro.tg','$2y$10$f41d038k3qee32WyjJGRTucpXBqFrD.mwylqfFQid2R8CwZlGqYLK','admin',NULL),
  (6,'livreur_tech','livreur@techvision.tg','$2y$10$xckap4MbKm0Q1jtgw1SYEuo/.JbliNCY8v7fvb2.5CBsHTgY/X/gm','livreur',1),
  (7,'livreur_fourni','livreur@fournibien.tg','$2y$10$xckap4MbKm0Q1jtgw1SYEuo/.JbliNCY8v7fvb2.5CBsHTgY/X/gm','livreur',2);
/*!40000 ALTER TABLE `utilisateur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vente`
--

DROP TABLE IF EXISTS `vente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vente` (
  `Id_Vente` int NOT NULL AUTO_INCREMENT,
  `Numero_Vente` varchar(50) NOT NULL,
  `Nom_Client` varchar(100) DEFAULT 'Client Comptant',
  `Nom_Vendeur` varchar(100) DEFAULT NULL,
  `Date_Vente` datetime DEFAULT CURRENT_TIMESTAMP,
  `Articles_JSON` text NOT NULL,
  `Montant_Total` decimal(10,2) NOT NULL,
  `Type_Vente` enum('directe','b2b') DEFAULT 'directe',
  `Id_Entreprise` int DEFAULT NULL,
  PRIMARY KEY (`Id_Vente`),
  UNIQUE KEY `Numero_Vente` (`Numero_Vente`),
  KEY `idx_vente_ent_date` (`Id_Entreprise`,`Date_Vente`),
  CONSTRAINT `vente_ibfk_1` FOREIGN KEY (`Id_Entreprise`) REFERENCES `entreprise` (`Id_Entreprise`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vente`
--

LOCK TABLES `vente` WRITE;
/*!40000 ALTER TABLE `vente` DISABLE KEYS */;
INSERT INTO `vente` VALUES (1,'VNT-20260801-0001','Kofi Mensah','alex_admin','2026-08-01 09:15:00','[{\"nom\":\"Écran PC 27\\\" FHD\",\"quantite\":1,\"prix_unitaire\":95000,\"sous_total\":95000},{\"nom\":\"Clavier mécanique RGB\",\"quantite\":1,\"prix_unitaire\":28500,\"sous_total\":28500}]',123500.00,'directe',1),(2,'VNT-20260805-0002','Ama Asante','marie_tech','2026-08-05 11:30:00','[{\"nom\":\"Souris sans fil\",\"quantite\":2,\"prix_unitaire\":14500,\"sous_total\":29000}]',29000.00,'directe',1),(3,'VNT-20260808-0003','Kwame Baffoe','alex_admin','2026-08-08 14:00:00','[{\"nom\":\"Hub USB 4 ports\",\"quantite\":1,\"prix_unitaire\":9800,\"sous_total\":9800},{\"nom\":\"Câble HDMI 2m\",\"quantite\":3,\"prix_unitaire\":4200,\"sous_total\":12600}]',22400.00,'directe',1),(4,'VNT-20260812-0004','Abena Osei','marie_tech','2026-08-12 10:45:00','[{\"nom\":\"Écran PC 27\\\" FHD\",\"quantite\":1,\"prix_unitaire\":95000,\"sous_total\":95000},{\"nom\":\"Souris sans fil\",\"quantite\":1,\"prix_unitaire\":14500,\"sous_total\":14500}]',109500.00,'directe',1),(5,'VNT-20260818-0005','Fiifi Mensah','alex_admin','2026-08-18 16:20:00','[{\"nom\":\"Clavier mécanique RGB\",\"quantite\":1,\"prix_unitaire\":28500,\"sous_total\":28500},{\"nom\":\"Câble HDMI 2m\",\"quantite\":2,\"prix_unitaire\":4200,\"sous_total\":8400}]',36900.00,'directe',1);
/*!40000 ALTER TABLE `vente` ENABLE KEYS */;
UNLOCK TABLES;

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

-- Dump completed on 2026-08-20 14:59:25
