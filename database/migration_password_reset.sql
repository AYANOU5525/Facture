-- Migration : Ajout de la table Password_Reset
-- À exécuter une seule fois dans phpMyAdmin ou via MySQL CLI

CREATE TABLE IF NOT EXISTS Password_Reset (
    Id_Reset      INT          AUTO_INCREMENT PRIMARY KEY,
    Id_Utilisateur INT         NOT NULL,
    Token         VARCHAR(64)  NOT NULL UNIQUE,
    Expire_At     DATETIME     NOT NULL,
    Utilise       BOOLEAN      DEFAULT FALSE,
    FOREIGN KEY (Id_Utilisateur) REFERENCES Utilisateur(Id_Utilisateur) ON DELETE CASCADE
) ENGINE=InnoDB;
