facturationutilisateurs
CREATE TABLE IF NOT EXISTS entreprises (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email_contact VARCHAR(100),
    nif_stat VARCHAR(50),
    logo_url VARCHAR(255),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nom_utilisateur VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'utilisateur',
    entreprise_id INTEGER,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS produits (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    quantite_en_stock INTEGER NOT NULL DEFAULT 0,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS ventes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_produit INTEGER NOT NULL,
    quantite INTEGER NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date DATETIME NOT NULL,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (id_produit) REFERENCES produits(id),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS depenses (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    description TEXT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date DATETIME NOT NULL,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS achats (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_produit INTEGER NOT NULL,
    quantite INTEGER NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date DATETIME NOT NULL,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (id_produit) REFERENCES produits(id),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS factures (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_facture VARCHAR(50) UNIQUE NOT NULL,
    nom_client VARCHAR(100) NOT NULL,
    email_client VARCHAR(100),
    date DATETIME NOT NULL,
    articles TEXT NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);

CREATE TABLE IF NOT EXISTS retours (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_produit INTEGER NOT NULL,
    quantite INTEGER NOT NULL,
    date_retour DATETIME NOT NULL,
    raison TEXT,
    cree_par VARCHAR(50) NOT NULL,
    entreprise_id INTEGER,
    FOREIGN KEY (id_produit) REFERENCES produits(id),
    FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)
);