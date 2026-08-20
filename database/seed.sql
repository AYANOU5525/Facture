-- ============================================================
-- SEED DATA — FactuPro (données de démonstration)
-- 2 entreprises, 4 utilisateurs, 10 produits, 5 ventes,
-- 3 commandes B2B, factures, logistique, annonces
-- Mot de passe admin  : Admin1234
-- Mot de passe employé: Employe123
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE Logistique;
TRUNCATE TABLE Facture;
TRUNCATE TABLE Chat_B2B;
TRUNCATE TABLE Notification_B2B;
TRUNCATE TABLE Ligne_Commande_B2B;
TRUNCATE TABLE Commande_B2B;
TRUNCATE TABLE Annonce;
TRUNCATE TABLE Vente;
TRUNCATE TABLE Produit;
TRUNCATE TABLE Password_Reset;
TRUNCATE TABLE Utilisateur;
TRUNCATE TABLE Entreprise;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ENTREPRISES
-- ============================================================
INSERT INTO Entreprise
    (Id_Entreprise, Nom_Entreprise, Adresse_Entreprise, Tel_Entreprise, Email_Entreprise,
     NIF_Entreprise, Secteur_Activite, Description_Entreprise,
     Score_Fiabilite, Nombre_Commandes_Completees,
     Latitude, Longitude, Ville, Region)
VALUES
    (1, 'TechVision Sarl',
     '24 Avenue de la Libération, Lomé',
     '+228 90 11 22 33',
     'contact@techvision.tg',
     'NIF-TG-2021-00142',
     'Informatique & Électronique',
     'Vente et distribution de matériel informatique et électronique grand public.',
     98, 12,
     6.1722, 1.2313, 'Lomé', 'Maritime'),

    (2, 'FourniBien SA',
     '8 Rue du Commerce, Lomé',
     '+228 91 44 55 66',
     'info@fournibien.tg',
     'NIF-TG-2019-00087',
     'Agroalimentaire & Négoce',
     'Grossiste en produits alimentaires et de grande consommation.',
     95, 18,
     6.1400, 1.2200, 'Lomé', 'Maritime');

-- ============================================================
-- UTILISATEURS  (Admin1234 / Employe123)
-- ============================================================
INSERT INTO Utilisateur
    (Id_Utilisateur, Nom_Utilisateur, Email_Utilisateur,
     Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise)
VALUES
    (1, 'alex_admin',   'alex@techvision.tg',
     '$2y$10$r/z6iUXGII3XB4BunN8LxOFKBxuotlZSKpnSAE9WlyfZUPBkfCTvW', 'admin', 1),

    (2, 'marie_tech',   'marie@techvision.tg',
     '$2y$10$uFzgG715mE5d8ommB.7KJ.EWpLyMnCRrSUVQBAK5gxrpwgGDry5Aq', 'utilisateur', 1),

    (3, 'fourni_admin', 'admin@fournibien.tg',
     '$2y$10$r/z6iUXGII3XB4BunN8LxOFKBxuotlZSKpnSAE9WlyfZUPBkfCTvW', 'admin', 2),

    (4, 'jean_fourni',  'jean@fournibien.tg',
     '$2y$10$uFzgG715mE5d8ommB.7KJ.EWpLyMnCRrSUVQBAK5gxrpwgGDry5Aq', 'utilisateur', 2);

-- ============================================================
-- PRODUITS — TechVision (Id_Entreprise = 1)
-- ============================================================
INSERT INTO Produit
    (Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit,
     Quantite_En_Stock, Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton,
     En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B, Id_Entreprise)
VALUES
    (1, 'Écran PC 27" FHD',
     'Moniteur Full HD 1920×1080, dalle IPS, 75 Hz, entrées HDMI/VGA',
     95000.00, 12,
     '3760001234001', '3760001234100', 4,
     TRUE, 85000.00, 2, 1),

    (2, 'Clavier mécanique RGB',
     'Switches blue, rétroéclairage RGB, disposition AZERTY',
     28500.00, 25,
     '3760001234002', '3760001234200', 10,
     TRUE, 24000.00, 5, 1),

    (3, 'Souris sans fil',
     'Capteur 1600 DPI, autonomie 12 mois, nano-récepteur USB',
     14500.00, 40,
     '3760001234003', NULL, 1,
     TRUE, 12000.00, 10, 1),

    (4, 'Câble HDMI 2m',
     'HDMI 2.0, 4K 60 Hz, blindage triple, contacts dorés',
     4200.00, 85,
     '3760001234004', '3760001234400', 20,
     FALSE, NULL, 1, 1),

    (5, 'Hub USB 4 ports',
     'USB 3.0, transfert jusqu à 5 Gbps, compatible PC/Mac',
     9800.00, 18,
     '3760001234005', NULL, 1,
     FALSE, NULL, 1, 1);

-- ============================================================
-- PRODUITS — FourniBien (Id_Entreprise = 2)
-- ============================================================
INSERT INTO Produit
    (Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit,
     Quantite_En_Stock, Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton,
     En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B, Id_Entreprise)
VALUES
    (6, 'Riz long grain 25 kg',
     'Riz blanc étuvé, sac 25 kg, origine Thaïlande',
     19500.00, 180,
     '6280001234006', '6280001234600', 5,
     TRUE, 17000.00, 5, 2),

    (7, 'Huile de palme 5 L',
     'Huile de palme raffinée, bidon 5 litres',
     6200.00, 250,
     '6280001234007', '6280001234700', 12,
     TRUE, 5400.00, 10, 2),

    (8, 'Sucre blanc 50 kg',
     'Sucre cristallisé, sac 50 kg, origine locale',
     31000.00, 120,
     '6280001234008', '6280001234800', 2,
     TRUE, 27500.00, 3, 2),

    (9, 'Savon ménage (carton 24u)',
     'Savon de lessive 400g, carton de 24 unités',
     10800.00, 60,
     '6280001234009', '6280001234900', 1,
     TRUE, 9500.00, 2, 2),

    (10, 'Farine de blé 25 kg',
     'Farine T55 tout usage, sac 25 kg',
     13500.00, 95,
     '6280001234010', '6280001235000', 4,
     TRUE, 11800.00, 5, 2);

-- ============================================================
-- VENTES — TechVision (5 ventes directes)
-- ============================================================
INSERT INTO Vente
    (Id_Vente, Numero_Vente, Nom_Client, Nom_Vendeur,
     Date_Vente, Articles_JSON, Montant_Total, Type_Vente, Id_Entreprise)
VALUES
    (1, 'VNT-20260801-0001', 'Kofi Mensah', 'alex_admin',
     '2026-08-01 09:15:00',
     '[{"nom":"Écran PC 27\\" FHD","quantite":1,"prix_unitaire":95000,"sous_total":95000},{"nom":"Clavier mécanique RGB","quantite":1,"prix_unitaire":28500,"sous_total":28500}]',
     123500.00, 'directe', 1),

    (2, 'VNT-20260805-0002', 'Ama Asante', 'marie_tech',
     '2026-08-05 11:30:00',
     '[{"nom":"Souris sans fil","quantite":2,"prix_unitaire":14500,"sous_total":29000}]',
     29000.00, 'directe', 1),

    (3, 'VNT-20260808-0003', 'Kwame Baffoe', 'alex_admin',
     '2026-08-08 14:00:00',
     '[{"nom":"Hub USB 4 ports","quantite":1,"prix_unitaire":9800,"sous_total":9800},{"nom":"Câble HDMI 2m","quantite":3,"prix_unitaire":4200,"sous_total":12600}]',
     22400.00, 'directe', 1),

    (4, 'VNT-20260812-0004', 'Abena Osei', 'marie_tech',
     '2026-08-12 10:45:00',
     '[{"nom":"Écran PC 27\\" FHD","quantite":1,"prix_unitaire":95000,"sous_total":95000},{"nom":"Souris sans fil","quantite":1,"prix_unitaire":14500,"sous_total":14500}]',
     109500.00, 'directe', 1),

    (5, 'VNT-20260818-0005', 'Fiifi Mensah', 'alex_admin',
     '2026-08-18 16:20:00',
     '[{"nom":"Clavier mécanique RGB","quantite":1,"prix_unitaire":28500,"sous_total":28500},{"nom":"Câble HDMI 2m","quantite":2,"prix_unitaire":4200,"sous_total":8400}]',
     36900.00, 'directe', 1);

-- ============================================================
-- COMMANDES B2B
-- ============================================================
-- C1 : FourniBien achète à TechVision → LIVRÉE
INSERT INTO Commande_B2B
    (Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
     Montant_Total, Date_Commande, Statut, Est_Urgente,
     Mode_Retrait, Date_Expedition_Reelle, Date_Validation, Message_Validation)
VALUES
    (1, 'CMD-B2B-20260730-001', 2, 1,
     265000.00, '2026-07-30 08:00:00', 'livree', FALSE,
     'livraison', '2026-08-02 09:00:00', '2026-07-31 10:00:00',
     'Commande validée, livraison prévue sous 48h.');

INSERT INTO Ligne_Commande_B2B
    (Id_Ligne, Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Quantite_Receptionnee, Prix_Unitaire, Sous_Total)
VALUES
    (1, 1, 2, 'Clavier mécanique RGB',  5, 5, 24000.00, 120000.00),
    (2, 1, 3, 'Souris sans fil',        10, 10, 12000.00, 120000.00),
    (3, 1, 4, 'Câble HDMI 2m',          6, 6, 4200.00,  25000.00);

-- C2 : FourniBien achète à TechVision → VALIDÉE (en cours)
INSERT INTO Commande_B2B
    (Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
     Montant_Total, Date_Commande, Statut, Est_Urgente,
     Mode_Retrait, Date_Validation, Message_Validation)
VALUES
    (2, 'CMD-B2B-20260815-002', 2, 1,
     170000.00, '2026-08-15 14:00:00', 'en_preparation', FALSE,
     'livraison', '2026-08-16 09:30:00',
     'En cours de préparation, expédition prévue d''ici 2 jours.');

INSERT INTO Ligne_Commande_B2B
    (Id_Ligne, Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Quantite_Receptionnee, Prix_Unitaire, Sous_Total)
VALUES
    (4, 2, 1, 'Écran PC 27" FHD', 2, 0, 85000.00, 170000.00);

-- C3 : TechVision achète à FourniBien → EXPÉDIÉE
INSERT INTO Commande_B2B
    (Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
     Montant_Total, Date_Commande, Statut, Est_Urgente,
     Mode_Retrait, Date_Expedition_Reelle, Date_Validation, Message_Validation)
VALUES
    (3, 'CMD-B2B-20260810-003', 1, 2,
     278000.00, '2026-08-10 10:00:00', 'expediee', TRUE,
     'livraison', '2026-08-13 07:30:00', '2026-08-11 08:00:00',
     'Commande urgente validée. Expédition effectuée le 13/08.');

INSERT INTO Ligne_Commande_B2B
    (Id_Ligne, Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Quantite_Receptionnee, Prix_Unitaire, Sous_Total)
VALUES
    (5, 3, 6, 'Riz long grain 25 kg', 10, 0, 17000.00, 170000.00),
    (6, 3, 7, 'Huile de palme 5 L',   20, 0,  5400.00, 108000.00);

-- ============================================================
-- FACTURES
-- ============================================================
INSERT INTO Facture
    (Id_Facture, Id_Vente, Id_Commande_B2B, Numero_Facture, Date_Facture,
     Date_Echeance, Statut_Paiement, Montant_HT, TVA, Montant_TTC,
     Date_Archivage, Id_Entreprise)
VALUES
    (1, 1, NULL, 'FAC-2026-0001', '2026-08-01 09:15:00',
     '2026-08-31 23:59:59', 'payee',    123500.00, 0.00, 123500.00,
     '2036-08-01 00:00:00', 1),

    (2, 2, NULL, 'FAC-2026-0002', '2026-08-05 11:30:00',
     '2026-09-04 23:59:59', 'payee',     29000.00, 0.00,  29000.00,
     '2036-08-05 00:00:00', 1),

    (3, 3, NULL, 'FAC-2026-0003', '2026-08-08 14:00:00',
     '2026-09-07 23:59:59', 'non_payee', 22400.00, 0.00,  22400.00,
     '2036-08-08 00:00:00', 1),

    (4, 4, NULL, 'FAC-2026-0004', '2026-08-12 10:45:00',
     '2026-09-11 23:59:59', 'payee',    109500.00, 0.00, 109500.00,
     '2036-08-12 00:00:00', 1),

    (5, 5, NULL, 'FAC-2026-0005', '2026-08-18 16:20:00',
     '2026-09-17 23:59:59', 'non_payee', 36900.00, 0.00,  36900.00,
     '2036-08-18 00:00:00', 1),

    (6, NULL, 1, 'FAC-2026-B001', '2026-08-02 09:00:00',
     '2026-09-01 23:59:59', 'payee',    265000.00, 0.00, 265000.00,
     '2036-08-02 00:00:00', 1),

    (7, NULL, 3, 'FAC-2026-B002', '2026-08-13 07:30:00',
     '2026-09-12 23:59:59', 'non_payee', 278000.00, 0.00, 278000.00,
     '2036-08-13 00:00:00', 2);

-- ============================================================
-- LOGISTIQUE
-- ============================================================
INSERT INTO Logistique
    (Id_Logistique, Id_Vente, Id_Commande_B2B, Id_Facture,
     Transporteur, Numero_Suivi, Statut_Livraison,
     Date_Expedition, Date_Livraison_Prevue, Date_Livraison_Effectuee,
     Adresse_Livraison, Adresse_Livraison_Lat, Adresse_Livraison_Lng,
     Notes_Logistique, Id_Entreprise)
VALUES
    -- Vente V4 → livrée
    (1, 4, NULL, 4,
     'Rapidex Express', 'RPX-20260812-4401', 'livree',
     '2026-08-12 15:00:00', '2026-08-13 12:00:00', '2026-08-13 10:30:00',
     'Quartier Bè, Rue des Palmiers, Lomé',
     6.1580, 1.2250,
     'Livraison effectuée sans incident. Colis remis en main propre.', 1),

    -- Commande B2B C1 → livrée
    (2, NULL, 1, 6,
     'TransLog Togo', 'TLT-20260802-0012', 'livree',
     '2026-08-02 09:00:00', '2026-08-04 17:00:00', '2026-08-04 14:15:00',
     '8 Rue du Commerce, Lomé',
     6.1400, 1.2200,
     'Livraison B2B — 16 colis au total, réception confirmée par Jean Fourni.', 1),

    -- Commande B2B C3 → expédiée
    (3, NULL, 3, 7,
     'Sahel Transport', 'STR-20260813-0089', 'expediee',
     '2026-08-13 07:30:00', '2026-08-15 12:00:00', NULL,
     '24 Avenue de la Libération, Lomé',
     6.1722, 1.2313,
     'Commande urgente — 30 unités. Livraison attendue le 15/08.', 2);

-- ============================================================
-- ANNONCES
-- ============================================================
INSERT INTO Annonce
    (Id_Annonce, Id_Entreprise, Type_Annonce, Titre, Description,
     Date_Publication, Statut)
VALUES
    (1, 1, 'appel_offre',
     'Recherche fournisseur accessoires PC — 500 unités/mois',
     'TechVision Sarl recherche un fournisseur régulier pour des accessoires informatiques (câbles, hubs, adaptateurs). Volume mensuel estimé : 500 unités. Délai de réponse : 15 jours.',
     '2026-08-10 08:00:00', 'active'),

    (2, 2, 'partenariat',
     'Partenariat distribution produits alimentaires — Région Maritime',
     'FourniBien SA propose un partenariat de distribution exclusive dans la région Maritime pour ses produits phares (riz, huile, sucre, farine). Nous cherchons des distributeurs fiables avec un réseau établi.',
     '2026-08-05 10:00:00', 'active'),

    (3, 1, 'partenariat',
     'Offre équipement bureautique — PME et administrations',
     'TechVision Sarl propose des offres groupées d''équipement bureautique pour les PME et organismes publics : écrans, claviers, souris, câbles. Remise à partir de 10 postes.',
     '2026-08-14 09:30:00', 'active');

-- ============================================================
-- NOTIFICATIONS B2B
-- ============================================================
INSERT INTO Notification_B2B
    (Id_Entreprise_Destinataire, Type_Notif, Titre, Message,
     Id_Commande_B2B, Est_Lue, Date_Creation)
VALUES
    (1, 'nouvelle_commande',
     'Nouvelle commande B2B reçue',
     'FourniBien SA vient de passer une commande de 265 000 FCFA (CMD-B2B-20260730-001).',
     1, TRUE, '2026-07-30 08:05:00'),

    (1, 'nouvelle_commande',
     'Nouvelle commande B2B reçue',
     'FourniBien SA vient de passer une commande de 170 000 FCFA (CMD-B2B-20260815-002).',
     2, FALSE, '2026-08-15 14:05:00'),

    (2, 'expedition',
     'Votre commande a été expédiée',
     'TechVision Sarl a expédié votre commande CMD-B2B-20260730-001 via Rapidex Express.',
     1, TRUE, '2026-08-02 09:10:00'),

    (2, 'nouvelle_commande',
     'Nouvelle commande B2B urgente',
     'TechVision Sarl a passé une commande urgente de 278 000 FCFA (CMD-B2B-20260810-003).',
     3, FALSE, '2026-08-10 10:05:00');

-- ============================================================
-- CHAT B2B
-- ============================================================
INSERT INTO Chat_B2B
    (Id_Commande_B2B, Id_Entreprise_Emetteur, Message, Type_Message,
     Est_Lu_Acheteur, Est_Lu_Vendeur, Date_Envoi)
VALUES
    (1, 2, 'Bonjour, pouvez-vous confirmer la disponibilité des 10 souris ?', 'texte', TRUE, TRUE, '2026-07-30 08:30:00'),
    (1, 1, 'Oui, tout est disponible en stock. Livraison sous 48h confirmée.', 'texte', TRUE, TRUE, '2026-07-30 09:00:00'),
    (1, 2, 'Parfait, merci. On attend la livraison.', 'texte', TRUE, TRUE, '2026-07-30 09:10:00'),

    (3, 1, 'Commande urgente — besoin du riz et de l''huile avant vendredi impérativement.', 'texte', TRUE, TRUE, '2026-08-10 10:10:00'),
    (3, 2, 'Reçu. Stock disponible. Nous expédions dès demain matin.', 'texte', TRUE, TRUE, '2026-08-10 11:00:00'),
    (3, 1, 'Super, merci beaucoup !', 'texte', FALSE, TRUE, '2026-08-10 11:05:00');
