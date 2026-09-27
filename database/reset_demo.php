<?php
/**
 * Script de réinitialisation complète de la base de données FactuPro.
 * Vide toutes les tables et recrée les données de démonstration
 * avec des mots de passe connus.
 *
 * Usage : php database/reset_demo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/../config/db.php';

echo "=== Réinitialisation de la base FactuPro ===\n\n";

// Désactiver les FK le temps du nettoyage
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Liste de toutes les tables à vider
$tables = [
    'Chat_B2B',
    'Notification_B2B',
    'Historique_Commande_B2B',
    'Logistique',
    'Facture',
    'Ligne_Commande_B2B',
    'Commande_B2B',
    'Annonce',
    'Audit_Log',
    'Password_Reset',
    'Contenir',
    'Ligne_Produit',
    'Ligne_Vente',
    'Vente',
    'Client',
    'Produit',
    'Email_Confirmation',
    'Utilisateur',
    'Entreprise',
];

foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
    echo "  ✓ Table '$t' vidée\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "\n";

// ============================================================
// ENTREPRISES
// ============================================================
$pdo->exec("
INSERT INTO Entreprise
    (Id_Entreprise, Nom_Entreprise, Adresse_Entreprise, Tel_Entreprise, Email_Entreprise,
     NIF_Entreprise, Secteur_Activite, Description_Entreprise,
     Score_Fiabilite, Nombre_Commandes_Completees,
     Latitude, Longitude, Ville, Region)
VALUES
    (1, 'TechVision Sarl',
     '24 Avenue de la Libération, Lomé', '+228 90 11 22 33', 'contact@techvision.tg',
     'NIF-TG-2021-00142', 'Informatique & Électronique',
     'Vente et distribution de matériel informatique et électronique grand public.',
     98, 12, 6.1722000, 1.2313000, 'Lomé', 'Maritime'),

    (2, 'FourniBien SA',
     '8 Rue du Commerce, Lomé', '+228 91 44 55 66', 'info@fournibien.tg',
     'NIF-TG-2019-00087', 'Agroalimentaire & Négoce',
     'Grossiste en produits alimentaires et de grande consommation.',
     95, 18, 6.1400000, 1.2200000, 'Lomé', 'Maritime')
");
echo "✓ 2 entreprises créées\n";

// ============================================================
// UTILISATEURS — mots de passe connus
// ============================================================
$users = [
    // --- Admin plateforme (aucune entreprise) ---
    ['superadmin',      'admin@factupro.tg',       'Super123!',   'admin',   null],

    // --- TechVision Sarl (Id_Entreprise = 1) ---
    ['alex_admin',      'alex@techvision.tg',      'Alex123!',    'proprio', 1],
    ['marie_tech',      'marie@techvision.tg',     'Marie123!',   'vendeur', 1],
    ['sophie_tech',     'sophie@techvision.tg',    'Sophie123!',  'vendeur', 1],
    ['paul_tech',       'paul@techvision.tg',      'Paul123!',    'vendeur', 1],
    ['livreur_tech',    'livreur@techvision.tg',   'Livreur123!', 'livreur', 1],
    ['moussa_tech',     'moussa@techvision.tg',    'Moussa123!',  'livreur', 1],

    // --- FourniBien SA (Id_Entreprise = 2) ---
    ['fourni_admin',    'admin@fournibien.tg',     'Fourni123!',  'proprio', 2],
    ['jean_fourni',     'jean@fournibien.tg',      'Jean123!',    'vendeur', 2],
    ['awa_fourni',      'awa@fournibien.tg',       'Awa123!',     'vendeur', 2],
    ['koffi_fourni',    'koffi@fournibien.tg',     'Koffi123!',   'vendeur', 2],
    ['livreur_fourni',  'livreur@fournibien.tg',   'Trans123!',   'livreur', 2],
    ['ama_fourni',      'ama@fournibien.tg',       'Ama123!',     'livreur', 2],
];

$stmt = $pdo->prepare(
    "INSERT INTO Utilisateur (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise)
     VALUES (?, ?, ?, ?, ?)"
);

foreach ($users as $u) {
    $hash = password_hash($u[2], PASSWORD_DEFAULT);
    $stmt->execute([$u[0], $u[1], $hash, $u[3], $u[4]]);
}
echo "✓ " . count($users) . " utilisateurs créés\n";

// ============================================================
// PRODUITS
// ============================================================
$pdo->exec("
INSERT INTO Produit
    (Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Quantite_En_Stock,
     Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton,
     En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B, Id_Entreprise, Seuil_Alerte_Stock)
VALUES
    (1,'Écran PC 27\" FHD','Moniteur Full HD 1920×1080, dalle IPS, 75 Hz',95000.00,12,'3760001234001','3760001234100',4,1,85000.00,2,1,5),
    (2,'Clavier mécanique RGB','Switches blue, rétroéclairage RGB, AZERTY',28500.00,25,'3760001234002','3760001234200',10,1,24000.00,5,1,5),
    (3,'Souris sans fil','Capteur 1600 DPI, autonomie 12 mois',14500.00,40,'3760001234003',NULL,1,1,12000.00,10,1,5),
    (4,'Câble HDMI 2m','HDMI 2.0, 4K 60 Hz, contacts dorés',4200.00,85,'3760001234004','3760001234400',20,0,NULL,1,1,5),
    (5,'Hub USB 4 ports','USB 3.0, transfert 5 Gbps, PC/Mac',9800.00,18,'3760001234005',NULL,1,0,NULL,1,1,5),
    (6,'Riz long grain 25 kg','Riz blanc étuvé, sac 25 kg, Thaïlande',19500.00,180,'6280001234006','6280001234600',5,1,17000.00,5,2,5),
    (7,'Huile de palme 5 L','Huile de palme raffinée, bidon 5 L',6200.00,250,'6280001234007','6280001234700',12,1,5400.00,10,2,5),
    (8,'Sucre blanc 50 kg','Sucre cristallisé, sac 50 kg',31000.00,120,'6280001234008','6280001234800',2,1,27500.00,3,2,5),
    (9,'Savon ménage (carton 24u)','Savon de lessive 400g, carton de 24',10800.00,60,'6280001234009','6280001234900',1,1,9500.00,2,2,5),
    (10,'Farine de blé 25 kg','Farine T55, sac 25 kg',13500.00,95,'6280001234010','6280001235000',4,1,11800.00,5,2,5)
");
echo "✓ 10 produits créés\n";

// ============================================================
// CATÉGORIES DE PRODUITS (Ligne_Produit + Contenir)
// ============================================================
$pdo->exec("
INSERT INTO Ligne_Produit (Id_Ligne_Produit, Id_Entreprise, Libelle)
VALUES
    (1,1,'Périphériques'),
    (2,1,'Accessoires'),
    (3,2,'Céréales & Farineux'),
    (4,2,'Épicerie')
");
$pdo->exec("
INSERT INTO Contenir (Id_Produit, Id_Ligne_Produit)
VALUES
    (1,1),
    (2,1),(2,2),
    (3,1),(3,2),
    (4,2),
    (5,2),
    (6,3),
    (7,4),
    (8,4),
    (9,4),
    (10,3)
");
echo "✓ 4 catégories de produits créées\n";

// ============================================================
// VENTES
// ============================================================
$pdo->exec("
INSERT INTO Vente (Id_Vente, Numero_Vente, Nom_Client, Nom_Vendeur, Date_Vente, Articles_JSON, Montant_Total, Type_Vente, Id_Entreprise)
VALUES
    (1,'VNT-20260801-0001','Kofi Mensah','alex_admin','2026-08-01 09:15:00',
     '[{\"nom\":\"Écran PC 27\\\\\" FHD\",\"quantite\":1,\"prix_unitaire\":95000,\"sous_total\":95000},{\"nom\":\"Clavier mécanique RGB\",\"quantite\":1,\"prix_unitaire\":28500,\"sous_total\":28500}]',
     123500.00,'directe',1),
    (2,'VNT-20260805-0002','Ama Asante','marie_tech','2026-08-05 11:30:00',
     '[{\"nom\":\"Souris sans fil\",\"quantite\":2,\"prix_unitaire\":14500,\"sous_total\":29000}]',
     29000.00,'directe',1),
    (3,'VNT-20260808-0003','Kwame Baffoe','sophie_tech','2026-08-08 14:00:00',
     '[{\"nom\":\"Hub USB 4 ports\",\"quantite\":1,\"prix_unitaire\":9800,\"sous_total\":9800},{\"nom\":\"Câble HDMI 2m\",\"quantite\":3,\"prix_unitaire\":4200,\"sous_total\":12600}]',
     22400.00,'directe',1),
    (4,'VNT-20260812-0004','Abena Osei','paul_tech','2026-08-12 10:45:00',
     '[{\"nom\":\"Écran PC 27\\\\\" FHD\",\"quantite\":1,\"prix_unitaire\":95000,\"sous_total\":95000},{\"nom\":\"Souris sans fil\",\"quantite\":1,\"prix_unitaire\":14500,\"sous_total\":14500}]',
     109500.00,'directe',1),
    (5,'VNT-20260818-0005','Fiifi Mensah','marie_tech','2026-08-18 16:20:00',
     '[{\"nom\":\"Clavier mécanique RGB\",\"quantite\":1,\"prix_unitaire\":28500,\"sous_total\":28500},{\"nom\":\"Câble HDMI 2m\",\"quantite\":2,\"prix_unitaire\":4200,\"sous_total\":8400}]',
     36900.00,'directe',1),
    (6,'VNT-20260803-0006','Yao Adjei','jean_fourni','2026-08-03 09:30:00',
     '[{\"nom\":\"Riz long grain 25 kg\",\"quantite\":2,\"prix_unitaire\":19500,\"sous_total\":39000}]',
     39000.00,'directe',2),
    (7,'VNT-20260807-0007','Akosua Mensah','awa_fourni','2026-08-07 13:15:00',
     '[{\"nom\":\"Huile de palme 5 L\",\"quantite\":3,\"prix_unitaire\":6200,\"sous_total\":18600},{\"nom\":\"Sucre blanc 50 kg\",\"quantite\":1,\"prix_unitaire\":31000,\"sous_total\":31000}]',
     49600.00,'directe',2),
    (8,'VNT-20260814-0008','Kodjo Agbeko','koffi_fourni','2026-08-14 16:00:00',
     '[{\"nom\":\"Savon ménage (carton 24u)\",\"quantite\":2,\"prix_unitaire\":10800,\"sous_total\":21600}]',
     21600.00,'directe',2),
    (9,'VNT-20260819-0009','Efua Boateng','fourni_admin','2026-08-19 10:20:00',
     '[{\"nom\":\"Farine de blé 25 kg\",\"quantite\":2,\"prix_unitaire\":13500,\"sous_total\":27000},{\"nom\":\"Riz long grain 25 kg\",\"quantite\":1,\"prix_unitaire\":19500,\"sous_total\":19500}]',
     46500.00,'directe',2)
");
echo "✓ 9 ventes créées\n";

// ============================================================
// CLIENTS DIRECTS (backfill depuis les ventes, cf. database/migrations.sql section 5)
// ============================================================
$pdo->exec("
INSERT INTO Client (Id_Entreprise, Nom_Client, Type_Client)
SELECT DISTINCT Id_Entreprise, Nom_Client, 'direct' FROM Vente
");
$pdo->exec("
UPDATE Vente v
JOIN Client c ON c.Id_Entreprise = v.Id_Entreprise AND c.Nom_Client = v.Nom_Client
SET v.Id_Client = c.Id_Client
");
echo "✓ Clients directs créés à partir des ventes\n";

// ============================================================
// COMMANDES B2B
// ============================================================
$pdo->exec("
INSERT INTO Commande_B2B
    (Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
     Montant_Total, Date_Commande, Statut, Est_Urgente, Mode_Retrait,
     Date_Limite_Reponse, Date_Expedition_Reelle, Date_Validation, Message_Validation)
VALUES
    (1,'CMD-B2B-20260730-001',2,1,265000.00,'2026-07-30 08:00:00','livree',0,'livraison',
     NULL,'2026-08-02 09:00:00','2026-07-31 10:00:00','Commande validée, livraison prévue sous 48h.'),
    (2,'CMD-B2B-20260815-002',2,1,170000.00,'2026-08-15 14:00:00','en_preparation',0,'livraison',
     NULL,NULL,'2026-08-16 09:30:00','En préparation, expédition prévue sous 2 jours.'),
    (3,'CMD-B2B-20260810-003',1,2,278000.00,'2026-08-10 10:00:00','expediee',1,'livraison',
     NULL,'2026-08-13 07:30:00','2026-08-11 08:00:00','Commande urgente validée. Expédition le 13/08.'),
    (4,'CMD-B2B-20260819-004',1,2,42600.00,'2026-08-19 09:00:00','en_attente',0,'livraison',
     '2026-08-19 11:00:00',NULL,NULL,NULL),
    (5,'CMD-B2B-20260820-005',2,1,48000.00,'2026-08-20 10:00:00','refusee',0,'livraison',
     NULL,NULL,'2026-08-20 11:30:00','Rupture de stock temporaire sur le clavier mécanique RGB, désolé.')
");
echo "✓ 5 commandes B2B créées\n";

// ============================================================
// LIGNES COMMANDE B2B
// ============================================================
$pdo->exec("
INSERT INTO Ligne_Commande_B2B (Id_Ligne, Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Quantite_Receptionnee, Prix_Unitaire, Sous_Total)
VALUES
    (1,1,2,'Clavier mécanique RGB',5,5,24000.00,120000.00),
    (2,1,3,'Souris sans fil',10,10,12000.00,120000.00),
    (3,1,4,'Câble HDMI 2m',6,6,4200.00,25000.00),
    (4,2,1,'Écran PC 27\" FHD',2,0,85000.00,170000.00),
    (5,3,6,'Riz long grain 25 kg',10,0,17000.00,170000.00),
    (6,3,7,'Huile de palme 5 L',20,0,5400.00,108000.00),
    (7,4,9,'Savon ménage (carton 24u)',2,0,9500.00,19000.00),
    (8,4,10,'Farine de blé 25 kg',2,0,11800.00,23600.00),
    (9,5,2,'Clavier mécanique RGB',2,0,24000.00,48000.00)
");
echo "✓ Lignes de commande B2B créées\n";

// ============================================================
// FACTURES
// ============================================================
$pdo->exec("
INSERT INTO Facture (Id_Facture, Id_Vente, Id_Commande_B2B, Numero_Facture, Date_Facture, Date_Echeance, Statut_Paiement, Montant_HT, TVA, Montant_TTC, Id_Entreprise, Date_Archivage)
VALUES
    (1,1,NULL,'FAC-2026-0001','2026-08-01 09:15:00','2026-08-31 23:59:59','payee',123500.00,0.00,123500.00,1,'2036-08-01 00:00:00'),
    (2,2,NULL,'FAC-2026-0002','2026-08-05 11:30:00','2026-09-04 23:59:59','payee',29000.00,0.00,29000.00,1,'2036-08-05 00:00:00'),
    (3,3,NULL,'FAC-2026-0003','2026-08-08 14:00:00','2026-09-07 23:59:59','non_payee',22400.00,0.00,22400.00,1,'2036-08-08 00:00:00'),
    (4,4,NULL,'FAC-2026-0004','2026-08-12 10:45:00','2026-09-11 23:59:59','payee',109500.00,0.00,109500.00,1,'2036-08-12 00:00:00'),
    (5,5,NULL,'FAC-2026-0005','2026-08-18 16:20:00','2026-09-17 23:59:59','non_payee',36900.00,0.00,36900.00,1,'2036-08-18 00:00:00'),
    (6,6,NULL,'FAC-2026-0006','2026-08-03 09:30:00','2026-09-02 23:59:59','payee',39000.00,0.00,39000.00,2,'2036-08-03 00:00:00'),
    (7,7,NULL,'FAC-2026-0007','2026-08-07 13:15:00','2026-09-06 23:59:59','non_payee',49600.00,0.00,49600.00,2,'2036-08-07 00:00:00'),
    (8,8,NULL,'FAC-2026-0008','2026-08-14 16:00:00','2026-09-13 23:59:59','payee',21600.00,0.00,21600.00,2,'2036-08-14 00:00:00'),
    (9,9,NULL,'FAC-2026-0009','2026-08-19 10:20:00','2026-09-18 23:59:59','non_payee',46500.00,0.00,46500.00,2,'2036-08-19 00:00:00'),
    (10,NULL,1,'FAC-2026-B001','2026-08-02 09:00:00','2026-09-01 23:59:59','payee',265000.00,0.00,265000.00,1,'2036-08-02 00:00:00'),
    (11,NULL,3,'FAC-2026-B002','2026-08-13 07:30:00','2026-09-12 23:59:59','non_payee',278000.00,0.00,278000.00,2,'2036-08-13 00:00:00')
");
$pdo->exec("
UPDATE Facture f
JOIN Vente v ON v.Id_Vente = f.Id_Vente
SET f.Id_Client = v.Id_Client
WHERE v.Id_Client IS NOT NULL
");
echo "✓ 11 factures créées\n";

// ============================================================
// LOGISTIQUE
// ============================================================
$pdo->exec("
INSERT INTO Logistique (Id_Logistique, Id_Vente, Id_Commande_B2B, Id_Facture, Transporteur, Numero_Suivi, Statut_Livraison, Date_Expedition, Date_Livraison_Prevue, Date_Livraison_Effectuee, Adresse_Livraison, Notes_Logistique, Id_Entreprise, Adresse_Livraison_Lat, Adresse_Livraison_Lng)
VALUES
    (1,4,NULL,4,'Rapidex Express','RPX-20260812-4401','livree','2026-08-12 15:00:00','2026-08-13 12:00:00','2026-08-13 10:30:00','Quartier Bè, Rue des Palmiers, Lomé','Livraison effectuée sans incident.',1,6.1580000,1.2250000),
    (2,8,NULL,8,'Sahel Transport','STR-20260814-2201','expediee','2026-08-14 16:30:00','2026-08-16 12:00:00',NULL,'Quartier Adidogomé, Lomé','En cours de livraison.',2,6.1550000,1.1900000),
    (3,NULL,1,10,'TransLog Togo','TLT-20260802-0012','livree','2026-08-02 09:00:00','2026-08-04 17:00:00','2026-08-04 14:15:00','8 Rue du Commerce, Lomé','Livraison B2B — réception confirmée.',1,6.1400000,1.2200000),
    (4,NULL,3,11,'Sahel Transport','STR-20260813-0089','expediee','2026-08-13 07:30:00','2026-08-15 12:00:00',NULL,'24 Avenue de la Libération, Lomé','Commande urgente — 30 unités.',2,6.1722000,1.2313000)
");
echo "✓ 4 entrées logistique créées\n";

// ============================================================
// ANNONCES
// ============================================================
$pdo->exec("
INSERT INTO Annonce (Id_Annonce, Id_Entreprise, Type_Annonce, Titre, Description, Date_Publication, Statut)
VALUES
    (1,1,'appel_offre','Recherche fournisseur accessoires PC — 500 unités/mois','TechVision Sarl recherche un fournisseur régulier pour des accessoires informatiques.','2026-08-10 08:00:00','active'),
    (2,2,'partenariat','Partenariat distribution produits alimentaires — Région Maritime','FourniBien SA propose un partenariat de distribution exclusive.','2026-08-05 10:00:00','active'),
    (3,1,'partenariat','Offre équipement bureautique — PME et administrations','Offres groupées pour PME et organismes publics.','2026-08-14 09:30:00','active')
");
echo "✓ 3 annonces créées\n";

// ============================================================
// NOTIFICATIONS B2B
// ============================================================
$pdo->exec("
INSERT INTO Notification_B2B (Id_Entreprise_Destinataire, Type_Notif, Titre, Message, Id_Commande_B2B, Est_Lue, Date_Creation)
VALUES
    (1,'nouvelle_commande','Nouvelle commande B2B reçue','FourniBien SA — 265 000 FCFA (CMD-B2B-20260730-001).',1,1,'2026-07-30 08:05:00'),
    (1,'nouvelle_commande','Nouvelle commande B2B reçue','FourniBien SA — 170 000 FCFA (CMD-B2B-20260815-002).',2,0,'2026-08-15 14:05:00'),
    (2,'expedition','Votre commande a été expédiée','TechVision Sarl a expédié CMD-B2B-20260730-001.',1,1,'2026-08-02 09:10:00'),
    (2,'nouvelle_commande','Nouvelle commande B2B urgente','TechVision Sarl — 278 000 FCFA (CMD-B2B-20260810-003).',3,0,'2026-08-10 10:05:00'),
    (2,'nouvelle_commande','Nouvelle commande B2B reçue','TechVision Sarl — 42 600 FCFA (CMD-B2B-20260819-004).',4,0,'2026-08-19 09:05:00'),
    (2,'refus','Commande CMD-B2B-20260820-005 refusée','TechVision Sarl a refusé votre commande : rupture de stock temporaire.',5,0,'2026-08-20 11:30:00')
");
echo "✓ 6 notifications B2B créées\n";

// ============================================================
// CHAT B2B
// ============================================================
$pdo->exec("
INSERT INTO Chat_B2B (Id_Commande_B2B, Id_Entreprise_Emetteur, Message, Type_Message, Est_Lu_Acheteur, Est_Lu_Vendeur, Date_Envoi)
VALUES
    (1,2,'Bonjour, pouvez-vous confirmer la disponibilité des 10 souris ?','texte',1,1,'2026-07-30 08:30:00'),
    (1,1,'Oui, tout est disponible. Livraison sous 48h confirmée.','texte',1,1,'2026-07-30 09:00:00'),
    (1,2,'Parfait, merci. On attend la livraison.','texte',1,1,'2026-07-30 09:10:00'),
    (3,1,'Commande urgente — besoin du riz et de l''huile avant vendredi.','texte',1,1,'2026-08-10 10:10:00'),
    (3,2,'Reçu. Stock disponible. Nous expédions demain matin.','texte',1,1,'2026-08-10 11:00:00'),
    (3,1,'Super, merci beaucoup !','texte',0,1,'2026-08-10 11:05:00'),
    (4,1,'Bonjour, seriez-vous disponible pour 2 cartons de savon et 2 sacs de farine ?','texte',1,0,'2026-08-19 09:02:00'),
    (4,1,'On reste flexible sur la date de livraison si besoin.','negociation_delai',1,0,'2026-08-19 09:03:00')
");
echo "✓ 8 messages chat B2B créés\n";

// ============================================================
echo "\n========================================\n";
echo "  RÉINITIALISATION TERMINÉE !\n";
echo "========================================\n\n";
echo "Comptes de connexion (" . count($users) . " utilisateurs) :\n\n";

$noms_entreprise = [1 => 'TechVision', 2 => 'FourniBien', null => '—'];
$labels_role = ['admin' => 'Admin plateforme', 'proprio' => 'Proprio', 'vendeur' => 'Vendeur', 'livreur' => 'Livreur'];

echo str_pad('ENTREPRISE', 14) . str_pad('RÔLE', 18) . str_pad('USERNAME', 18) . str_pad('EMAIL', 28) . "MOT DE PASSE\n";
echo str_repeat('-', 98) . "\n";
foreach ($users as [$username, $email, $password, $role, $id_entreprise]) {
    echo str_pad($noms_entreprise[$id_entreprise], 14)
        . str_pad($labels_role[$role] ?? $role, 18)
        . str_pad($username, 18)
        . str_pad($email, 28)
        . "$password\n";
}
echo "\n";
