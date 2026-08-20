-- ============================================================
-- Migration : Améliorations FactuPro
-- À exécuter une seule fois dans l'ordre indiqué
-- ============================================================

-- 1. Table de journalisation des actions (Audit Log)
CREATE TABLE IF NOT EXISTS `Audit_Log` (
    `Id_Log`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Id_Utilisateur`  INT UNSIGNED NOT NULL,
    `Id_Entreprise`   INT UNSIGNED NOT NULL,
    `Action`          VARCHAR(100) NOT NULL,
    `Table_Cible`     VARCHAR(100) DEFAULT NULL,
    `Id_Cible`        INT UNSIGNED  DEFAULT NULL,
    `Details`         TEXT          DEFAULT NULL,
    `IP_Address`      VARCHAR(45)   DEFAULT NULL,
    `Created_At`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_user`       (`Id_Utilisateur`),
    INDEX `idx_log_entreprise` (`Id_Entreprise`),
    INDEX `idx_log_created`    (`Created_At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Colonne Seuil_Alerte_Stock (ajout conditionnel via procédure — MySQL 8.x)
DROP PROCEDURE IF EXISTS _add_col_seuil;
DELIMITER $$
CREATE PROCEDURE _add_col_seuil()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME  = 'Produit'
          AND COLUMN_NAME = 'Seuil_Alerte_Stock'
    ) THEN
        ALTER TABLE `Produit`
            ADD COLUMN `Seuil_Alerte_Stock` INT UNSIGNED NOT NULL DEFAULT 5
                COMMENT 'Alerte si Quantite_En_Stock <= seuil';
    END IF;
END$$
DELIMITER ;
CALL _add_col_seuil();
DROP PROCEDURE IF EXISTS _add_col_seuil;

-- 3. Index de performance (ajout conditionnel via procédure)
DROP PROCEDURE IF EXISTS _add_indexes;
DELIMITER $$
CREATE PROCEDURE _add_indexes()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Vente'        AND INDEX_NAME = 'idx_vente_ent_date')      THEN CREATE INDEX `idx_vente_ent_date`         ON `Vente`        (`Id_Entreprise`, `Date_Vente`);      END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Produit'      AND INDEX_NAME = 'idx_produit_ent')           THEN CREATE INDEX `idx_produit_ent`           ON `Produit`      (`Id_Entreprise`);                      END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Commande_B2B' AND INDEX_NAME = 'idx_cmd_b2b_vendeuse_statut') THEN CREATE INDEX `idx_cmd_b2b_vendeuse_statut` ON `Commande_B2B` (`Id_Entreprise_Vendeuse`, `Statut`); END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Logistique'   AND INDEX_NAME = 'idx_logistique_ent_statut')  THEN CREATE INDEX `idx_logistique_ent_statut`  ON `Logistique`   (`Id_Entreprise`, `Statut_Livraison`);  END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Facture'      AND INDEX_NAME = 'idx_facture_ent_statut')     THEN CREATE INDEX `idx_facture_ent_statut`     ON `Facture`      (`Id_Entreprise`, `Statut_Paiement`);   END IF;
END$$
DELIMITER ;
CALL _add_indexes();
DROP PROCEDURE IF EXISTS _add_indexes;
