CREATE DATABASE IF NOT EXISTS facturation;
USE facturation;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS Logistique;
DROP TABLE IF EXISTS Facture;
DROP TABLE IF EXISTS Chat_B2B;
DROP TABLE IF EXISTS Notification_B2B;
DROP TABLE IF EXISTS Ligne_Commande_B2B;
DROP TABLE IF EXISTS Commande_B2B;
DROP TABLE IF EXISTS Annonce;
DROP TABLE IF EXISTS Vente;
DROP TABLE IF EXISTS Produit;
DROP TABLE IF EXISTS Utilisateur;
DROP TABLE IF EXISTS Entreprise;

SET FOREIGN_KEY_CHECKS = 1;

-- Table ENTREPRISE (Contient le score et les stats B2B)
CREATE TABLE Entreprise (
    Id_Entreprise INT AUTO_INCREMENT PRIMARY KEY,
    Nom_Entreprise VARCHAR(100) NOT NULL,
    Adresse_Entreprise VARCHAR(200),
    Tel_Entreprise VARCHAR(20),
    Email_Entreprise VARCHAR(100),
    NIF_Entreprise VARCHAR(50),
    Secteur_Activite VARCHAR(100),
    Description_Entreprise TEXT,
    Score_Fiabilite INT DEFAULT 100,
    Nombre_Commandes_Completees INT DEFAULT 0,
    Latitude DECIMAL(10, 7) NULL,
    Longitude DECIMAL(10, 7) NULL,
    Ville VARCHAR(100) NULL,
    Region VARCHAR(100) NULL
) ENGINE=InnoDB;


CREATE TABLE Utilisateur (
    Id_Utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    Nom_Utilisateur VARCHAR(50) NOT NULL UNIQUE,
    Email_Utilisateur VARCHAR(100) NOT NULL UNIQUE,
    Mot_De_Passe_Utilisateur VARCHAR(255) NOT NULL,
    Role_Utilisateur ENUM('admin', 'utilisateur') DEFAULT 'utilisateur',
    Id_Entreprise INT,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE Produit (
    Id_Produit INT AUTO_INCREMENT PRIMARY KEY,
    Nom_Produit VARCHAR(100) NOT NULL,
    Description_Produit TEXT,
    Prix_Unitaire_Produit DECIMAL(10,2) NOT NULL,
    Quantite_En_Stock INT DEFAULT 0,
    Code_Barre_Unite VARCHAR(100) NULL,
    Code_Barre_Carton VARCHAR(100) NULL,
    Quantite_Par_Carton INT DEFAULT 1,
    En_Destockage_B2B BOOLEAN DEFAULT FALSE,
    Prix_B2B DECIMAL(10,2),
    Quantite_Min_B2B INT DEFAULT 1,
    Id_Entreprise INT,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Vente (
    Id_Vente INT AUTO_INCREMENT PRIMARY KEY,
    Numero_Vente VARCHAR(50) UNIQUE NOT NULL,
    Nom_Client VARCHAR(100) DEFAULT 'Client Comptant',
    Nom_Vendeur VARCHAR(100),
    Date_Vente DATETIME DEFAULT CURRENT_TIMESTAMP,
    Articles_JSON TEXT NOT NULL,
    Montant_Total DECIMAL(10,2) NOT NULL,
    Type_Vente ENUM('directe', 'b2b') DEFAULT 'directe',
    Id_Entreprise INT,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Annonce (
    Id_Annonce INT AUTO_INCREMENT PRIMARY KEY,
    Id_Entreprise INT,
    Type_Annonce ENUM('appel_offre', 'partenariat') NOT NULL,
    Titre VARCHAR(200) NOT NULL,
    Description TEXT,
    Date_Publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    Statut ENUM('active', 'expiree', 'terminee') DEFAULT 'active',
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Commande_B2B (
    Id_Commande_B2B INT AUTO_INCREMENT PRIMARY KEY,
    Numero_Commande VARCHAR(50) UNIQUE NOT NULL,
    Id_Entreprise_Acheteuse INT,
    Id_Entreprise_Vendeuse INT,
    Articles_JSON TEXT NULL COMMENT '[DEPRECATED] Conservé pour rétrocompatibilité',
    Montant_Total DECIMAL(10,2) NOT NULL,
    Date_Commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    Statut ENUM('en_attente', 'validee', 'en_preparation', 'prete', 'expediee', 'livree', 'refusee') DEFAULT 'en_attente',
    Est_Urgente BOOLEAN DEFAULT FALSE,
    Delai_Reponse_Minutes INT DEFAULT 120,
    Date_Limite_Reponse DATETIME NULL,
    Mode_Retrait ENUM('livraison', 'retrait_place') DEFAULT 'livraison',
    Adresse_Retrait TEXT NULL,
    Date_Expedition_Reelle DATETIME NULL,
    Message_Validation TEXT,
    Date_Validation DATETIME,
    FOREIGN KEY (Id_Entreprise_Acheteuse) REFERENCES Entreprise(Id_Entreprise),
    FOREIGN KEY (Id_Entreprise_Vendeuse) REFERENCES Entreprise(Id_Entreprise)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Ligne_Commande_B2B (
    Id_Ligne INT AUTO_INCREMENT PRIMARY KEY,
    Id_Commande_B2B INT NOT NULL,
    Id_Produit INT NOT NULL,
    Nom_Produit VARCHAR(200) NOT NULL,
    Quantite INT NOT NULL,
    Quantite_Receptionnee INT NOT NULL DEFAULT 0,
    Prix_Unitaire DECIMAL(10, 2) NOT NULL,
    Sous_Total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE CASCADE,
    FOREIGN KEY (Id_Produit) REFERENCES Produit(Id_Produit) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Notification_B2B (
    Id_Notification INT AUTO_INCREMENT PRIMARY KEY,
    Id_Entreprise_Destinataire INT NOT NULL,
    Type_Notif ENUM('nouvelle_commande', 'commande_urgente', 'nouveau_message', 'validation', 'refus', 'preparation', 'prete', 'livraison', 'expedition', 'reception') NOT NULL,
    Titre VARCHAR(200) NOT NULL,
    Message TEXT,
    Id_Commande_B2B INT NULL,
    Est_Lue BOOLEAN DEFAULT FALSE,
    Date_Creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Id_Entreprise_Destinataire) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Chat_B2B (
    Id_Message INT AUTO_INCREMENT PRIMARY KEY,
    Id_Commande_B2B INT NOT NULL,
    Id_Entreprise_Emetteur INT NOT NULL,
    Message TEXT NULL,
    Type_Message ENUM('texte', 'negociation_qte', 'negociation_delai', 'confirmation_dispo', 'fichier') DEFAULT 'texte',
    Fichier_Path VARCHAR(500) NULL,
    Fichier_Nom VARCHAR(255) NULL,
    Est_Lu_Acheteur BOOLEAN DEFAULT FALSE,
    Est_Lu_Vendeur BOOLEAN DEFAULT FALSE,
    Date_Envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE CASCADE,
    FOREIGN KEY (Id_Entreprise_Emetteur) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table FACTURE (Détails financiers et suivi des paiements)
-- OBLIGATION LÉGALE : toute facture doit être conservée 10 ans minimum (Art. L123-22 et équivalents locaux)
CREATE TABLE Facture (
    Id_Facture INT AUTO_INCREMENT PRIMARY KEY,
    Id_Vente INT DEFAULT NULL,
    Id_Commande_B2B INT DEFAULT NULL,
    Numero_Facture VARCHAR(50) UNIQUE NOT NULL,
    Date_Facture DATETIME DEFAULT CURRENT_TIMESTAMP,
    Date_Echeance DATETIME,
    Statut_Paiement ENUM('non_payee', 'payee', 'en_retard', 'annulee') DEFAULT 'non_payee',
    Montant_HT DECIMAL(10,2),
    TVA DECIMAL(10,2) DEFAULT 0,
    Montant_TTC DECIMAL(10,2) NOT NULL,
    Date_Archivage DATETIME NOT NULL COMMENT 'Date limite légale de conservation = Date_Facture + 10 ans',
    Id_Entreprise INT,
    FOREIGN KEY (Id_Vente) REFERENCES Vente(Id_Vente) ON DELETE SET NULL,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE SET NULL,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE RESTRICT,
    INDEX idx_facture_date (Date_Facture),
    INDEX idx_facture_archivage (Date_Archivage)
) ENGINE=InnoDB;

-- Table LOGISTIQUE (Suivi des expéditions et livraisons)
CREATE TABLE Logistique (
    Id_Logistique INT AUTO_INCREMENT PRIMARY KEY,
    Id_Vente INT DEFAULT NULL,
    Id_Commande_B2B INT DEFAULT NULL,
    Id_Facture INT DEFAULT NULL,
    Transporteur VARCHAR(100),
    Numero_Suivi VARCHAR(100),
    Statut_Livraison ENUM('traitement', 'en_attente', 'expediee', 'livree', 'annulee') DEFAULT 'traitement',
    Date_Expedition DATETIME,
    Date_Livraison_Prevue DATETIME,
    Date_Livraison_Effectuee DATETIME,
    Adresse_Livraison TEXT,
    Adresse_Livraison_Lat DECIMAL(10,7) NULL,
    Adresse_Livraison_Lng DECIMAL(10,7) NULL,
    Notes_Logistique TEXT,
    Id_Entreprise INT,
    FOREIGN KEY (Id_Vente) REFERENCES Vente(Id_Vente) ON DELETE SET NULL,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE SET NULL,
    FOREIGN KEY (Id_Facture) REFERENCES Facture(Id_Facture) ON DELETE SET NULL,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;
