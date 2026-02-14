Table ENTREPRISE (Contient le score et les stats B2B)
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
    Nombre_Commandes_Completees INT DEFAULT 0
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

CREATE TABLE Message (
    Id_Message INT AUTO_INCREMENT PRIMARY KEY,
    Id_Expediteur INT NOT NULL,
    Id_Destinataire INT NOT NULL,
    Contenu TEXT NOT NULL,
    Date_Envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    Lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (Id_Expediteur) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE,
    FOREIGN KEY (Id_Destinataire) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;



CREATE TABLE Produit (
    Id_Produit INT AUTO_INCREMENT PRIMARY KEY,
    Nom_Produit VARCHAR(100) NOT NULL,
    Description_Produit TEXT,
    Prix_Unitaire_Produit DECIMAL(10,2) NOT NULL,
    Quantite_En_Stock INT DEFAULT 0,
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
    Articles_JSON TEXT NOT NULL,
    Montant_Total DECIMAL(10,2) NOT NULL,
    Date_Commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    Statut ENUM('en_attente', 'validee', 'expediee', 'livree', 'refusee') DEFAULT 'en_attente',
    Message_Validation TEXT,
    Date_Validation DATETIME,
    FOREIGN KEY (Id_Entreprise_Acheteuse) REFERENCES Entreprise(Id_Entreprise),
    FOREIGN KEY (Id_Entreprise_Vendeuse) REFERENCES Entreprise(Id_Entreprise)
) ENGINE=InnoDB;

-- Table FACTURE (Détails financiers et suivi des paiements)
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
    Id_Entreprise INT,
    FOREIGN KEY (Id_Vente) REFERENCES Vente(Id_Vente) ON DELETE SET NULL,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE SET NULL,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
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
    Notes_Logistique TEXT,
    Id_Entreprise INT,
    FOREIGN KEY (Id_Vente) REFERENCES Vente(Id_Vente) ON DELETE SET NULL,
    FOREIGN KEY (Id_Commande_B2B) REFERENCES Commande_B2B(Id_Commande_B2B) ON DELETE SET NULL,
    FOREIGN KEY (Id_Facture) REFERENCES Facture(Id_Facture) ON DELETE SET NULL,
    FOREIGN KEY (Id_Entreprise) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
) ENGINE=InnoDB;
