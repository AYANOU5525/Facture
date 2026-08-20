-- ============================================================
-- Migration : Ajout des rôles métier
-- À exécuter une seule fois
-- ============================================================

-- Étendre l'ENUM Role_Utilisateur avec les nouveaux rôles
ALTER TABLE `Utilisateur`
    MODIFY COLUMN `Role_Utilisateur`
        ENUM('admin', 'proprio', 'vendeur', 'livreur', 'utilisateur')
        NOT NULL DEFAULT 'vendeur'
        COMMENT 'admin=app admin, proprio=owner, vendeur=salesperson, livreur=delivery';

-- Les anciens comptes 'utilisateur' deviennent 'vendeur' par défaut
UPDATE `Utilisateur` SET `Role_Utilisateur` = 'vendeur' WHERE `Role_Utilisateur` = 'utilisateur';
