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
--   6) Livraison structurée : livreur assigné, double confirmation livreur/acheteur, N° de suivi attribué (2026-10-09)
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

-- 6) Livraison structurée -----------------------------------------------------
--    « Expédier » ouvre la fiche de livraison (N° de suivi attribué par le système) ; le
--    transporteur (livreur de l'équipe ou transporteur externe) et la date prévue sont
--    obligatoires pour valider l'expédition. La livraison n'est close qu'une fois confirmée
--    par le livreur ET par l'acheteur (dans n'importe quel ordre).
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Logistique' AND COLUMN_NAME = 'Id_Livreur');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Logistique ADD COLUMN Id_Livreur INT NULL AFTER Transporteur,
         ADD KEY Id_Livreur (Id_Livreur),
         ADD CONSTRAINT logistique_livreur_fk FOREIGN KEY (Id_Livreur) REFERENCES Utilisateur (Id_Utilisateur) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Logistique' AND COLUMN_NAME = 'Date_Confirmation_Livreur');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Logistique ADD COLUMN Date_Confirmation_Livreur DATETIME NULL AFTER Date_Livraison_Effectuee,
         ADD COLUMN Date_Confirmation_Acheteur DATETIME NULL AFTER Date_Confirmation_Livreur',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

--    Reprise des fiches existantes (chaque requête ne touche que ce qui n'est pas encore repris).
--    « En attente d'enlèvement » disparaît : ces fiches redeviennent « à planifier ».
UPDATE Logistique SET Statut_Livraison = 'traitement' WHERE Statut_Livraison = 'en_attente';
ALTER TABLE Logistique
    MODIFY Statut_Livraison ENUM('traitement','expediee','livree','annulee') DEFAULT 'traitement';
--    Commande déjà expédiée par l'ancien parcours alors que sa fiche était restée « en préparation ».
UPDATE Logistique l JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
SET l.Statut_Livraison = 'expediee', l.Date_Expedition = COALESCE(l.Date_Expedition, c.Date_Expedition_Reelle, NOW())
WHERE l.Statut_Livraison = 'traitement' AND c.Statut IN ('expediee', 'livree');
--    Fiches livrées : la remise par le livreur est datée de la livraison.
UPDATE Logistique
SET Date_Confirmation_Livreur = COALESCE(Date_Livraison_Effectuee, Date_Expedition, NOW())
WHERE Statut_Livraison = 'livree' AND Date_Confirmation_Livreur IS NULL;
--    Commandes dont l'acheteur a confirmé la réception.
UPDATE Logistique l JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
SET l.Date_Confirmation_Acheteur = COALESCE(l.Date_Livraison_Effectuee, NOW())
WHERE c.Statut = 'livree' AND l.Date_Confirmation_Acheteur IS NULL;
UPDATE Logistique l JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
SET l.Statut_Livraison = 'livree', l.Date_Livraison_Effectuee = COALESCE(l.Date_Livraison_Effectuee, l.Date_Confirmation_Acheteur)
WHERE c.Statut = 'livree' AND l.Statut_Livraison = 'expediee';
--    Remise confirmée par le livreur mais pas encore par l'acheteur : la livraison reste en cours.
UPDATE Logistique l JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
SET l.Statut_Livraison = 'expediee', l.Date_Livraison_Effectuee = NULL
WHERE l.Statut_Livraison = 'livree' AND c.Statut = 'expediee';
--    N° de suivi attribué aux fiches qui n'en ont pas (même forme que la numérotation : LIV-AAAAMMJJ-NNNN).
UPDATE Logistique
SET Numero_Suivi = CONCAT('LIV-', DATE_FORMAT(COALESCE(Date_Expedition, NOW()), '%Y%m%d'), '-', LPAD(Id_Logistique, 4, '0'))
WHERE Numero_Suivi IS NULL OR TRIM(Numero_Suivi) = '';
