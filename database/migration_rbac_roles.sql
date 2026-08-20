-- ============================================================
-- Migration RBAC : refonte du système de rôles FactuPro
-- Date : 2026-08-20
-- Contexte : passage de 2 rôles (admin/utilisateur) à 4 rôles
--            (admin, proprio, vendeur, livreur)
-- ============================================================

-- 1. Modifier l'ENUM pour supporter les 4 rôles
ALTER TABLE Utilisateur
MODIFY COLUMN Role_Utilisateur ENUM('admin','proprio','vendeur','livreur') NOT NULL DEFAULT 'proprio';

-- 2. Convertir les anciens "admin" d'entreprise en "proprio"
--    (ces utilisateurs étaient les gérants de leur entreprise,
--     pas des admins de la plateforme)
UPDATE Utilisateur
SET Role_Utilisateur = 'proprio'
WHERE Role_Utilisateur = 'admin'
  AND Id_Entreprise IS NOT NULL;

-- 3. Convertir les anciens "utilisateur" en "vendeur"
--    (les employés d'entreprise sont des vendeurs par défaut)
UPDATE Utilisateur
SET Role_Utilisateur = 'vendeur'
WHERE Role_Utilisateur = 'utilisateur';

-- 4. Créer le vrai administrateur de la plateforme (sans entreprise)
--    Identifiants : superadmin / Admin2025!
INSERT INTO Utilisateur
  (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise)
VALUES
  ('superadmin', 'admin@factupro.tg',
   '$2y$10$f41d038k3qee32WyjJGRTucpXBqFrD.mwylqfFQid2R8CwZlGqYLK',
   'admin', NULL);

-- 5. Créer des livreurs de démonstration pour chaque entreprise
--    Identifiants : livreur_tech / Livreur2025!  &  livreur_fourni / Livreur2025!
INSERT INTO Utilisateur
  (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise)
VALUES
  ('livreur_tech',   'livreur@techvision.tg',  '$2y$10$xckap4MbKm0Q1jtgw1SYEuo/.JbliNCY8v7fvb2.5CBsHTgY/X/gm', 'livreur', 1),
  ('livreur_fourni', 'livreur@fournibien.tg',  '$2y$10$xckap4MbKm0Q1jtgw1SYEuo/.JbliNCY8v7fvb2.5CBsHTgY/X/gm', 'livreur', 2);

-- ============================================================
-- Résultat attendu après migration :
--
--  superadmin   | admin   | NULL (admin plateforme)
--  alex_admin   | proprio | 1    (gérant TechVision)
--  marie_tech   | vendeur | 1    (vendeuse TechVision)
--  livreur_tech | livreur | 1    (livreur TechVision)
--  fourni_admin | proprio | 2    (gérant FourniBien)
--  jean_fourni  | vendeur | 2    (vendeur FourniBien)
--  livreur_fourni| livreur| 2    (livreur FourniBien)
-- ============================================================
