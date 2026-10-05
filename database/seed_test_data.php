<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

function seedInsert(PDO $pdo, string $table, array $values): int
{
    $columns = array_keys($values);
    $quotedColumns = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $statement = $pdo->prepare("INSERT INTO `{$table}` ({$quotedColumns}) VALUES ({$placeholders})");
    $statement->execute(array_values($values));

    return (int) $pdo->lastInsertId();
}

$companies = [
    [
        'name' => 'Agro-Services des Plateaux SARL',
        'sector' => 'Agriculture et agroalimentaire',
        'city' => 'Kpalime',
        'region' => 'Plateaux',
        'lat' => '6.9000',
        'lng' => '0.6333',
        'username' => 'test_agro',
        'email' => 'agro@example.test',
        'nif' => 'TEST-NIF-AGRO-001',
        'description' => 'Entreprise fictive de collecte et distribution de produits agricoles dans la region des Plateaux au Togo.',
        'categories' => ['Cereales et legumes secs', 'Huiles et produits transformes'],
        'products' => [
            ['Riz local, sac 25 kg', 'Riz local trie et conditionne en sac de 25 kg.', 16000, 80, 0],
            ['Haricots secs, sac 10 kg', 'Haricots secs calibres pour commerces et restauration.', 11000, 55, 0],
            ['Huile d arachide, bidon 5 L', 'Huile artisanale filtree, conditionnee en bidon scelle.', 9000, 4, 1],
        ],
    ],
    [
        'name' => 'Confection du Golfe SARL',
        'sector' => 'Textile et habillement',
        'city' => 'Lome',
        'region' => 'Maritime',
        'lat' => '6.1372',
        'lng' => '1.2125',
        'username' => 'test_textile',
        'email' => 'textile@example.test',
        'nif' => 'TEST-NIF-TEXT-002',
        'description' => 'Atelier fictif de confection de vetements de travail et de petites series textiles.',
        'categories' => ['Vetements professionnels', 'Accessoires textiles'],
        'products' => [
            ['Polo de travail brode', 'Polo coton-polyester, tailles S a XXL.', 7500, 65, 0],
            ['Tablier de cuisine renforce', 'Tablier coton avec poche frontale et liens reglables.', 6500, 42, 0],
            ['Sac reutilisable en coton, lot de 10', 'Sacs cousus localement, format courses, lot de 10.', 20000, 4, 1],
        ],
    ],
    [
        'name' => 'Materiaux de Construction Centrale SARL',
        'sector' => 'Materiaux de construction',
        'city' => 'Sokode',
        'region' => 'Centrale',
        'lat' => '8.9833',
        'lng' => '1.1333',
        'username' => 'test_batiment',
        'email' => 'batiment@example.test',
        'nif' => 'TEST-NIF-BATI-003',
        'description' => 'Negociant fictif en materiaux de construction pour chantiers et quincailleries.',
        'categories' => ['Gros oeuvre', 'Quincaillerie'],
        'products' => [
            ['Ciment Portland, sac 50 kg', 'Ciment polyvalent pour maconnerie et beton.', 4500, 120, 0],
            ['Fer a beton 8 mm, barre 6 m', 'Barre nervuree pour ouvrages en beton arme.', 3000, 95, 0],
            ['Bloc creux 15 x 20 x 40 cm', 'Bloc beton creux pour murs et cloisons.', 600, 4, 1],
        ],
    ],
    [
        'name' => 'Energie Solaire Kara SARL',
        'sector' => 'Materiel electrique et energie solaire',
        'city' => 'Kara',
        'region' => 'Kara',
        'lat' => '9.5511',
        'lng' => '1.1861',
        'username' => 'test_solaire',
        'email' => 'solaire@example.test',
        'nif' => 'TEST-NIF-VOLT-004',
        'description' => 'Distributeur fictif de petits equipements electriques et de solutions solaires.',
        'categories' => ['Eclairage solaire', 'Installation electrique'],
        'products' => [
            ['Lampe solaire autonome 5 W', 'Lampe LED avec panneau integre et batterie rechargeable.', 11000, 38, 0],
            ['Cable electrique 2,5 mm2, rouleau 100 m', 'Cable cuivre isole pour installation interieure.', 60000, 24, 0],
            ['Kit de connecteurs solaires, lot de 20', 'Connecteurs etanches pour petites installations PV.', 5000, 4, 1],
        ],
    ],
    [
        'name' => 'Distribution Hygiene des Savanes SARL',
        'sector' => 'Hygiene et fournitures professionnelles',
        'city' => 'Dapaong',
        'region' => 'Savanes',
        'lat' => '10.8623',
        'lng' => '0.2076',
        'username' => 'test_hygiene',
        'email' => 'hygiene@example.test',
        'nif' => 'TEST-NIF-HYG-005',
        'description' => 'Fournisseur fictif de produits d hygiene et de consommables pour commerces et collectivites.',
        'categories' => ['Hygiene corporelle', 'Entretien professionnel'],
        'products' => [
            ['Savon doux, carton de 48', 'Savon surgras pour usage quotidien, carton de 48 unites.', 14000, 52, 0],
            ['Gel hydroalcoolique, carton de 12 x 500 ml', 'Gel desinfectant pour les mains, carton scelle.', 18000, 31, 0],
            ['Nettoyant multi-usage, bidon 5 L', 'Detergent concentre pour surfaces lavables.', 5000, 4, 1],
        ],
    ],
];

$usernameList = array_column($companies, 'username');
$emailList = array_column($companies, 'email');
$placeholders = implode(', ', array_fill(0, count($usernameList), '?'));
$existingSeeds = $pdo->prepare("SELECT COUNT(*) FROM Utilisateur WHERE Nom_Utilisateur IN ({$placeholders}) OR Email_Utilisateur IN ({$placeholders})");
$existingSeeds->execute([...$usernameList, ...$emailList]);
if ((int) $existingSeeds->fetchColumn() > 0) {
    fwrite(STDERR, "Les comptes de test existent deja; aucune donnee n'a ete modifiee.\n");
    exit(1);
}

$existingCompany = $pdo->prepare('SELECT Id_Entreprise FROM Entreprise WHERE Nom_Entreprise = ?');
$existingCompany->execute(['Horizon IVATO']);
$existingCompanyId = $existingCompany->fetchColumn();
if (!$existingCompanyId) {
    fwrite(STDERR, "L'entreprise deja presente n'a pas ete trouvee; aucune donnee n'a ete modifiee.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $companyIds = [];
    $userIds = [];
    $productIds = [];
    $passwordHash = password_hash('FactuTest2026!', PASSWORD_DEFAULT);

    foreach ($companies as $index => $company) {
        $companyIds[$index] = seedInsert($pdo, 'Entreprise', [
            'Nom_Entreprise' => $company['name'],
            'Adresse_Entreprise' => 'Adresse fictive - zone commerciale, ' . $company['city'],
            'Email_Entreprise' => $company['email'],
            'NIF_Entreprise' => $company['nif'],
            'Secteur_Activite' => $company['sector'],
            'Description_Entreprise' => $company['description'],
            'Score_Fiabilite' => 94 - $index,
            'Nombre_Commandes_Completees' => 3 + $index,
            'Latitude' => $company['lat'],
            'Longitude' => $company['lng'],
            'Ville' => $company['city'],
            'Region' => $company['region'],
        ]);

        $userIds[$index] = seedInsert($pdo, 'Utilisateur', [
            'Nom_Utilisateur' => $company['username'],
            'Email_Utilisateur' => $company['email'],
            'Email_Verifie' => 1,
            'Mot_De_Passe_Utilisateur' => $passwordHash,
            'Role_Utilisateur' => 'proprio',
            'Id_Entreprise' => $companyIds[$index],
        ]);

        $categoryIds = [];
        foreach ($company['categories'] as $category) {
            $categoryIds[] = seedInsert($pdo, 'Ligne_Produit', [
                'Id_Entreprise' => $companyIds[$index],
                'Libelle' => $category,
            ]);
        }

        $productIds[$index] = [];
        foreach ($company['products'] as $productIndex => $product) {
            $productId = seedInsert($pdo, 'Produit', [
                'Nom_Produit' => $product[0],
                'Description_Produit' => $product[1],
                'Prix_Unitaire_Produit' => $product[2],
                'Quantite_En_Stock' => $product[3],
                'Code_Barre_Unite' => 'TEST-' . ($index + 1) . '-UNIT-' . ($productIndex + 1),
                'Code_Barre_Carton' => 'TEST-' . ($index + 1) . '-BOX-' . ($productIndex + 1),
                'Quantite_Par_Carton' => $productIndex === 0 ? 1 : 6,
                'En_Destockage_B2B' => 1,
                'Prix_B2B' => round($product[2] * 0.88),
                'Quantite_Min_B2B' => 5,
                'Id_Entreprise' => $companyIds[$index],
                'Seuil_Alerte_Stock' => 5,
            ]);
            $productIds[$index][] = $productId;
            $categoryId = $categoryIds[$productIndex === 0 ? 0 : 1];
            seedInsert($pdo, 'Contenir', [
                'Id_Produit' => $productId,
                'Id_Ligne_Produit' => $categoryId,
            ]);
        }

        $clients = [];
        foreach ([
            ['nom' => 'Epicerie Kanto ' . ($index + 1), 'type' => 'entreprise'],
            ['nom' => 'Client comptoir ' . ($index + 1), 'type' => 'direct'],
        ] as $clientIndex => $client) {
            $clients[] = seedInsert($pdo, 'Client', [
                'Id_Entreprise' => $companyIds[$index],
                'Nom_Client' => $client['nom'],
                'Email_Client' => 'client' . ($index + 1) . '-' . ($clientIndex + 1) . '@example.test',
                'Adresse_Client' => 'Adresse fictive, ' . $company['city'],
                'Type_Client' => $client['type'],
                'Statut_Client' => 'actif',
            ]);
        }

        $saleDate = new DateTimeImmutable('-' . ($index + 1) . ' days');
        $saleId = seedInsert($pdo, 'Vente', [
            'Numero_Vente' => sprintf('VT-TEST-%02d-001', $index + 1),
            'Nom_Client' => 'Epicerie Kanto ' . ($index + 1),
            'Id_Client' => $clients[0],
            'Nom_Vendeur' => $company['username'],
            'Id_Vendeur' => $userIds[$index],
            'Date_Vente' => $saleDate->format('Y-m-d H:i:s'),
            'Montant_Total' => 0,
            'Type_Vente' => 'directe',
            'Id_Entreprise' => $companyIds[$index],
        ]);

        foreach ([[0, 2], [1, 3]] as [$productIndex, $quantity]) {
            $product = $company['products'][$productIndex];
            seedInsert($pdo, 'Ligne_Vente', [
                'Id_Vente' => $saleId,
                'Id_Produit' => $productIds[$index][$productIndex],
                'Nom_Produit' => $product[0],
                'Quantite' => $quantity,
                'Prix_Unitaire' => $product[2],
            ]);
            $stock = $pdo->prepare('UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock - ? WHERE Id_Produit = ?');
            $stock->execute([$quantity, $productIds[$index][$productIndex]]);
        }

        $total = (float) $pdo->query('SELECT Montant_Total FROM Vente WHERE Id_Vente = ' . $saleId)->fetchColumn();
        $tax = round($total * 0.2, 2);
        seedInsert($pdo, 'Facture', [
            'Id_Vente' => $saleId,
            'Id_Client' => $clients[0],
            'Numero_Facture' => sprintf('FAC-TEST-%02d-001', $index + 1),
            'Date_Facture' => $saleDate->format('Y-m-d H:i:s'),
            'Date_Echeance' => $saleDate->modify('+30 days')->format('Y-m-d H:i:s'),
            'Statut_Paiement' => $index === 4 ? 'non_payee' : 'payee',
            'Montant_HT' => $total,
            'TVA' => $tax,
            'Montant_TTC' => $total + $tax,
            'Id_Entreprise' => $companyIds[$index],
            'Date_Archivage' => $saleDate->modify('+10 years')->format('Y-m-d H:i:s'),
        ]);

        seedInsert($pdo, 'Annonce', [
            'Id_Entreprise' => $companyIds[$index],
            'Type_Annonce' => $index % 2 === 0 ? 'appel_offre' : 'partenariat',
            'Titre' => $index % 2 === 0 ? 'Recherche de fournisseurs - ' . $company['sector'] : 'Partenariat de distribution - ' . $company['city'],
            'Description' => 'Annonce fictive de test : prise de contact entre entreprises pour des volumes et conditions a negocier.',
            'Statut' => 'active',
        ]);
    }

    $orderCases = [
        ['seller' => 0, 'buyer' => 'company:1', 'status' => 'en_attente', 'days' => 0],
        ['seller' => 1, 'buyer' => 'company:2', 'status' => 'validee', 'days' => 1],
        ['seller' => 2, 'buyer' => 'company:3', 'status' => 'en_preparation', 'days' => 2],
        ['seller' => 3, 'buyer' => 'company:4', 'status' => 'expediee', 'days' => 4],
        ['seller' => 4, 'buyer' => 'company:0', 'status' => 'livree', 'days' => 7],
        ['seller' => 0, 'buyer' => 'existing', 'status' => 'refusee', 'days' => 2],
    ];

    foreach ($orderCases as $index => $order) {
        $sellerIndex = $order['seller'];
        $sellerId = $companyIds[$sellerIndex];
        $buyerId = $order['buyer'] === 'existing'
            ? (int) $existingCompanyId
            : $companyIds[(int) substr($order['buyer'], strlen('company:'))];
        $productIndex = 1;
        $product = $companies[$sellerIndex]['products'][$productIndex];
        $quantity = 5;
        $unitPrice = (int) round($product[2] * 0.88);
        $subtotal = $quantity * $unitPrice;
        $orderDate = $index === 0 ? new DateTimeImmutable('-45 minutes') : new DateTimeImmutable('-' . $order['days'] . ' days');
        $status = $order['status'];
        $validated = in_array($status, ['validee', 'en_preparation', 'expediee', 'livree'], true);
        $shipped = in_array($status, ['expediee', 'livree'], true);

        $orderId = seedInsert($pdo, 'Commande_B2B', [
            'Numero_Commande' => sprintf('B2B-TEST-2026-%03d', $index + 1),
            'Id_Entreprise_Acheteuse' => $buyerId,
            'Id_Entreprise_Vendeuse' => $sellerId,
            'Articles_JSON' => json_encode([['nom' => $product[0], 'quantite' => $quantity, 'prix_unitaire' => $unitPrice]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'Montant_Total' => 0,
            'Date_Commande' => $orderDate->format('Y-m-d H:i:s'),
            'Statut' => $status,
            'Est_Urgente' => $index === 2 ? 1 : 0,
            'Delai_Reponse_Minutes' => 120,
            'Date_Limite_Reponse' => $orderDate->modify('+120 minutes')->format('Y-m-d H:i:s'),
            'Mode_Retrait' => $index % 2 === 0 ? 'livraison' : 'retrait_place',
            'Adresse_Livraison' => $index % 2 === 0 ? 'Adresse fictive de reception, ' . $companies[$sellerIndex]['city'] : null,
            'Date_Expedition_Reelle' => $shipped ? $orderDate->modify('+1 day')->format('Y-m-d H:i:s') : null,
            'Message_Validation' => $validated ? 'Commande de test confirmee par le fournisseur.' : null,
            'Date_Validation' => $validated ? $orderDate->modify('+1 hour')->format('Y-m-d H:i:s') : null,
        ]);

        seedInsert($pdo, 'Ligne_Commande_B2B', [
            'Id_Commande_B2B' => $orderId,
            'Id_Produit' => $productIds[$sellerIndex][$productIndex],
            'Nom_Produit' => $product[0],
            'Quantite' => $quantity,
            'Quantite_Receptionnee' => $status === 'livree' ? $quantity : 0,
            'Prix_Unitaire' => $unitPrice,
            'Sous_Total' => $subtotal,
        ]);

        seedInsert($pdo, 'Historique_Commande_B2B', [
            'Id_Commande_B2B' => $orderId,
            'Ancien_Statut' => $status === 'en_attente' ? null : 'en_attente',
            'Nouveau_Statut' => $status,
            'Note' => 'Etape initiale de la commande fictive de test.',
            'Id_Entreprise_Action' => $sellerId,
            'Date_Changement' => $orderDate->format('Y-m-d H:i:s'),
        ]);

        foreach ([[$buyerId, 'Bonjour, nous souhaitons confirmer la disponibilite de ce lot.'], [$sellerId, 'Bonjour, demande recue. Voici les conditions de preparation proposees.']] as [$senderId, $message]) {
            seedInsert($pdo, 'Chat_B2B', [
                'Id_Commande_B2B' => $orderId,
                'Id_Entreprise_Emetteur' => $senderId,
                'Message' => $message,
                'Type_Message' => 'texte',
                'Est_Lu_Acheteur' => 0,
                'Est_Lu_Vendeur' => 0,
                'Date_Envoi' => $orderDate->modify('+10 minutes')->format('Y-m-d H:i:s'),
            ]);
        }

        seedInsert($pdo, 'Notification_B2B', [
            'Id_Entreprise_Destinataire' => $sellerId,
            'Type_Notif' => 'nouvelle_commande',
            'Titre' => 'Nouvelle commande B2B de test',
            'Message' => 'Une entreprise partenaire a passe une commande fictive.',
            'Id_Commande_B2B' => $orderId,
            'Est_Lue' => 0,
            'Date_Creation' => $orderDate->format('Y-m-d H:i:s'),
        ]);

        if ($shipped) {
            seedInsert($pdo, 'Logistique', [
                'Id_Commande_B2B' => $orderId,
                'Transporteur' => 'Transport Test Mada',
                'Numero_Suivi' => sprintf('TRK-TEST-%03d', $index + 1),
                'Statut_Livraison' => $status === 'livree' ? 'livree' : 'expediee',
                'Date_Expedition' => $orderDate->modify('+1 day')->format('Y-m-d H:i:s'),
                'Date_Livraison_Prevue' => $orderDate->modify('+5 days')->format('Y-m-d H:i:s'),
                'Date_Livraison_Effectuee' => $status === 'livree' ? $orderDate->modify('+5 days')->format('Y-m-d H:i:s') : null,
                'Adresse_Livraison' => 'Adresse fictive de reception',
                'Notes_Logistique' => 'Donnee de transport fictive pour test.',
                'Id_Entreprise' => $sellerId,
            ]);
        }
    }

    $pdo->commit();
    echo "Donnees de test ajoutees : 5 entreprises, 5 comptes, 15 produits, 10 clients, 5 ventes/factures et 6 commandes B2B.\n";
    echo "Les donnees preexistantes ont ete conservees. Comptes : " . implode(', ', $usernameList) . "\n";
    echo "Mot de passe commun des comptes ajoutes : FactuTest2026!\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Ajout annule (rollback) : ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}