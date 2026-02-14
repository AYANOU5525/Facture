<?php
require_once __DIR__ . '/../config/db.php';

// Augmenter le temps d'exécution pour le seeding
set_time_limit(300);

echo "<h1>Initialisation de la Base de Données...</h1>";

try {
    // 1. Désactiver les vérifications de clés étrangères et vider les tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables = [
        'Entreprise',
        'Utilisateur',
        'Produit',
        'Vente',
        'Annonce',
        'Commande_B2B',
        'Facture',
        'Logistique',
        'Message'
    ];

    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE $table");
        echo "Table <strong>$table</strong> vidée.<br>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<hr>";

    // 2. Création des Entreprises
    $entreprises = [
        [
            'Nom' => 'TechAudio SA',
            'Adresse' => '12 Rue de la Technologie, 75011 Paris',
            'Tel' => '0102030405',
            'Email' => 'contact@techaudio.com',
            'Secteur' => 'Électronique',
            'Desc' => 'Spécialiste du matériel audio professionnel et grand public.'
        ],
        [
            'Nom' => 'Bureau Design',
            'Adresse' => '45 Avenue du Meuble, 69002 Lyon',
            'Tel' => '0478965412',
            'Email' => 'info@bureaudesign.fr',
            'Secteur' => 'Mobilier',
            'Desc' => 'Mobilier de bureau ergonomique et moderne.'
        ],
        [
            'Nom' => 'Mode & Style',
            'Adresse' => '8 Boulevard de la Mode, 13008 Marseille',
            'Tel' => '0612345678',
            'Email' => 'hello@modestyle.com',
            'Secteur' => 'Textile',
            'Desc' => 'Grossiste en prêt-à-porter homme et femme.'
        ],
        [
            'Nom' => 'LogiTrans Services',
            'Adresse' => 'Zone Industrielle Nord, 59000 Lille',
            'Tel' => '0320558899',
            'Email' => 'contact@logitrans.com',
            'Secteur' => 'Logistique',
            'Desc' => 'Solutions de transport et logistique pour entreprises.'
        ]
    ];

    $entrepriseIds = [];

    $stmtEnt = $pdo->prepare("INSERT INTO Entreprise (Nom_Entreprise, Adresse_Entreprise, Tel_Entreprise, Email_Entreprise, Secteur_Activite, Description_Entreprise) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($entreprises as $ent) {
        $stmtEnt->execute([$ent['Nom'], $ent['Adresse'], $ent['Tel'], $ent['Email'], $ent['Secteur'], $ent['Desc']]);
        $entrepriseIds[$ent['Nom']] = $pdo->lastInsertId();
        echo "Entreprise créée : " . $ent['Nom'] . "<br>";
    }

    // 3. Création des Utilisateurs
    $password = password_hash('123456', PASSWORD_DEFAULT); // Mot de passe par défaut

    $users = [
        ['dave', 'dave@techaudio.com', 'TechAudio SA'], // Admin principal pour le test
        ['sarah', 'sarah@bureaudesign.fr', 'Bureau Design'],
        ['julien', 'julien@modestyle.com', 'Mode & Style'],
        ['martine', 'martine@logitrans.com', 'LogiTrans Services']
    ];

    $stmtUser = $pdo->prepare("INSERT INTO Utilisateur (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) VALUES (?, ?, ?, 'admin', ?)");

    foreach ($users as $u) {
        $stmtUser->execute([$u[0], $u[1], $password, $entrepriseIds[$u[2]]]);
        echo "Utilisateur créé : " . $u[0] . " (Mdp: 123456)<br>";
    }

    // 4. Création des Produits
    $productsData = [
        'TechAudio SA' => [
            ['Casque Sony WH-1000XM5', 'Casque à réduction de bruit sans fil.', 350000, 290000, 50],
            ['Enceinte JBL Flip 6', 'Enceinte portable étanche.', 120000, 95000, 100],
            ['Microphone Blue Yeti', 'Micro USB pour streaming et podcast.', 140000, 110000, 30],
            ['AirPods Pro 2', 'Écouteurs sans fil Apple.', 270000, 230000, 10],
            ['Câble HDMI 2.1 3m', 'Câble haute vitesse 8K.', 25000, 15000, 200],
            ['Barre de son Samsung', 'Barre de son avec caisson de basse.', 450000, 380000, 15]
        ],
        'Bureau Design' => [
            ['Chaise Ergonomique Pro', 'Support lombaire ajustable, accoudoirs 3D.', 180000, 140000, 40],
            ['Bureau Assis-Debout Électrique', 'Plateau 160x80cm, double moteur.', 350000, 280000, 20],
            ['Caisson 3 Tiroirs', 'Caisson mobile verrouillable.', 85000, 60000, 60],
            ['Lampe LED Architecte', 'Bras articulé, température réglable.', 45000, 30000, 80]
        ],
        'Mode & Style' => [
            ['T-Shirt Coton Bio H', '100% Coton, Coupe Droite.', 15000, 8000, 500],
            ['Jean Slim Fit Brut', 'Denim résistant, élasthanne.', 45000, 25000, 200],
            ['Sweat à Capuche', 'Molleton gratté, poche kangourou.', 35000, 20000, 150],
            ['Veste Blazer Navy', 'Coupe cintrée, doublure satin.', 85000, 55000, 80]
        ]
    ];

    $stmtProd = $pdo->prepare("INSERT INTO Produit (Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Prix_B2B, Quantite_En_Stock, En_Destockage_B2B, Id_Entreprise) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $allProductIds = [];

    foreach ($productsData as $companyName => $prods) {
        $entId = $entrepriseIds[$companyName];
        foreach ($prods as $p) {
            $isB2B = rand(0, 1); // Random destockage
            $stmtProd->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $isB2B, $entId]);
            $allProductIds[] = $pdo->lastInsertId();
        }
        echo "Produits ajoutés pour $companyName.<br>";
    }

    // 5. Génération de Ventes (Clients Directs)
    echo "<hr>Génération des ventes historiques...<br>";
    $stmtVente = $pdo->prepare("INSERT INTO Vente (Numero_Vente, Nom_Client, Articles_JSON, Montant_Total, Date_Vente, Id_Entreprise) VALUES (?, ?, ?, ?, ?, ?)");

    for ($i = 0; $i < 50; $i++) {
        // Choisir une entreprise au hasard
        $entName = array_rand($productsData);
        // Skip logistique for sales
        if ($entName == 'LogiTrans Services') continue;

        $entId = $entrepriseIds[$entName];

        // Date aléatoire dans les 30 derniers jours
        $date = date('Y-m-d H:i:s', strtotime("-" . rand(0, 30) . " days -" . rand(0, 24) . " hours"));

        // Générer un panier
        $montant = 0;
        $panier = [];
        $nbItems = rand(1, 4);

        for ($k = 0; $k < $nbItems; $k++) {
            $prod = $productsData[$entName][array_rand($productsData[$entName])];
            $qty = rand(1, 3);
            $panier[] = [
                'id' => 0, // Mock ID
                'nom' => $prod[0],
                'prix' => $prod[2],
                'quantite' => $qty
            ];
            $montant += $prod[2] * $qty;
        }

        $numVente = 'V-' . date('Ymd', strtotime($date)) . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $client = "Client " . rand(100, 999);

        $stmtVente->execute([$numVente, $client, json_encode($panier), $montant, $date, $entId]);
    }
    echo "50 Ventes générées.<br>";

    // 6. Génération de Commandes B2B
    echo "<hr>Génération des Commandes B2B...<br>";
    $stmtCmd = $pdo->prepare("INSERT INTO Commande_B2B (Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse, Articles_JSON, Montant_Total, Date_Commande, Statut) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // TechAudio achète à Bureau Design (mobilier pour bureau)
    $buyerId = $entrepriseIds['TechAudio SA'];
    $sellerId = $entrepriseIds['Bureau Design'];

    $cmds = [
        ['statut' => 'livree', 'date' => '-10 days', 'items' => [['nom' => 'Chaise Ergonomique Pro', 'prix' => 140000, 'quantite' => 5]]],
        ['statut' => 'en_attente', 'date' => '-1 hour', 'items' => [['nom' => 'Bureau Assis-Debout', 'prix' => 280000, 'quantite' => 2]]]
    ];

    foreach ($cmds as $idx => $cmd) {
        $total = 0;
        foreach ($cmd['items'] as $it) $total += $it['prix'] * $it['quantite'];
        $num = 'CMD-B2B-' . rand(1000, 9999);
        $date = date('Y-m-d H:i:s', strtotime($cmd['date']));
        $stmtCmd->execute([$num, $buyerId, $sellerId, json_encode($cmd['items']), $total, $date, $cmd['statut']]);
    }

    echo "Commandes B2B générées.<br>";

    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9;'>";
    echo "<h1 style='color: #2c3e50;'>Base de données initialisée avec succès !</h1>";
    echo "<p>Voici les comptes utilisateurs créés pour vos tests (Mot de passe unique : <strong>123456</strong>) :</p>";

    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
    echo "<tr style='background: #34495e; color: white;'>
            <th style='padding: 10px; text-align: left;'>Entreprise</th>
            <th style='padding: 10px; text-align: left;'>Utilisateur</th>
            <th style='padding: 10px; text-align: left;'>Email (Login)</th>
            <th style='padding: 10px; text-align: left;'>Secteur</th>
          </tr>";

    foreach ($users as $u) {
        $entNom = $u[2];
        $sector = '';
        foreach ($entreprises as $e) {
            if ($e['Nom'] === $entNom) {
                $sector = $e['Secteur'];
                break;
            }
        }

        echo "<tr style='border-bottom: 1px solid #ddd;'>";
        echo "<td style='padding: 10px;'><strong>$entNom</strong></td>";
        echo "<td style='padding: 10px;'>" . ucfirst($u[0]) . "</td>";
        echo "<td style='padding: 10px; color: #d35400;'><strong>{$u[1]}</strong></td>";
        echo "<td style='padding: 10px;'>$sector</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div style='margin-top: 30px; text-align: center;'>";
    echo "<a href='../login.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Se connecter maintenant</a>";
    echo "</div>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Erreur</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
