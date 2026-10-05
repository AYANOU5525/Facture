-- ============================================================================
-- FactuPro — Mises à jour d'une base EXISTANTE (fichier unique)
--
-- `facturation.sql` installe une base neuve déjà à jour. Ce fichier-ci ne sert qu'à
-- mettre à niveau une base installée avant ces changements, SANS perte de données.
-- Rejouable sans risque : chaque bloc vérifie s'il a déjà été appliqué.
--
-- Import : mysql -u root --default-character-set=utf8mb4 facturation < database/migrations.sql
--
-- Sommaire
--   1) Email_Confirmation.Tentatives — limite d'essais du code de confirmation (2026-10-05)
--   2) Factures : Montant_HT / TVA recalculés au taux de 18 % (2026-10-05)
--   3) Score de fiabilité B2B recalculé ((livrées + 5) / (tranchées + 5)) (2026-10-05)
--   4) Livraison partielle B2B : statuts a_confirmer / annulee + Ligne_Commande_B2B.Quantite_Proposee (2026-10-05)
--   5) Reliquat B2B : Commande_B2B.Id_Commande_Origine (2026-10-05)
-- Toute nouvelle mise à jour s'ajoute à la suite, avec une entrée dans ce sommaire.
-- ============================================================================

-- 1) Email_Confirmation.Tentatives ------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Email_Confirmation' AND COLUMN_NAME = 'Tentatives');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Email_Confirmation ADD COLUMN Tentatives TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER Utilise',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Factures : HT et TVA déduits du TTC (taux 18 %, cf. src/Application/Billing/Vat.php)
--    Auparavant Montant_HT = TTC × 0,8 (soit 25 %) et TVA = 0. Idempotent : recalcul pur.
UPDATE Facture
SET Montant_HT = ROUND(Montant_TTC / 1.18, 2),
    TVA        = Montant_TTC - ROUND(Montant_TTC / 1.18, 2);

-- 3) Score de fiabilité = (livrées + 5) / (livrées + refusées + 5) — crédit de départ de
--    5 commandes réussies (OrderRepository::RELIABILITY_PRIOR), 100 sans commande tranchée
UPDATE Entreprise e
LEFT JOIN (
    SELECT Id_Entreprise_Vendeuse AS id,
           SUM(Statut = 'livree') AS livrees,
           SUM(Statut IN ('livree', 'refusee')) AS tranchees
    FROM Commande_B2B
    GROUP BY Id_Entreprise_Vendeuse
) s ON s.id = e.Id_Entreprise
SET e.Nombre_Commandes_Completees = COALESCE(s.livrees, 0),
    e.Score_Fiabilite = ROUND(100 * (COALESCE(s.livrees, 0) + 5) / (COALESCE(s.tranchees, 0) + 5));

-- 4) Livraison partielle B2B -------------------------------------------------
--    a_confirmer : le vendeur propose des quantités réduites, en attente de l'acheteur ;
--    annulee     : commande annulée par l'acheteur (ne pénalise pas le vendeur).
--    MODIFY rejouable tel quel (même définition).
ALTER TABLE Commande_B2B
    MODIFY Statut ENUM('en_attente','a_confirmer','validee','en_preparation','prete','expediee','livree','refusee','annulee')
    DEFAULT 'en_attente';
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Ligne_Commande_B2B' AND COLUMN_NAME = 'Quantite_Proposee');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Ligne_Commande_B2B ADD COLUMN Quantite_Proposee INT NULL AFTER Quantite',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Reliquat B2B -------------------------------------------------------------
--    L'acheteur accepte une livraison partielle et demande que le reste soit complété plus
--    tard : le manque devient une nouvelle commande, reliée à celle d'origine.
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Commande_B2B' AND COLUMN_NAME = 'Id_Commande_Origine');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Commande_B2B ADD COLUMN Id_Commande_Origine INT NULL AFTER Date_Validation,
         ADD KEY Id_Commande_Origine (Id_Commande_Origine),
         ADD CONSTRAINT commande_b2b_origine_fk FOREIGN KEY (Id_Commande_Origine) REFERENCES Commande_B2B (Id_Commande_B2B) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
