<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!canSell()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit();
}

// Rate limiting : 60 requêtes par minute par IP
(function () {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = sys_get_temp_dir() . '/factupro_rate_' . md5($ip) . '.json';
    $now = date('YmdHi'); // fenêtre d'une minute

    $data = file_exists($key) ? json_decode(@file_get_contents($key), true) : null;

    if (is_array($data) && ($data['window'] ?? '') === $now) {
        if (($data['count'] ?? 0) >= 60) {
            http_response_code(429);
            echo json_encode(['error' => 'Trop de requêtes. Réessayez dans une minute.']);
            exit;
        }
        $data['count']++;
    } else {
        $data = ['window' => $now, 'count' => 1];
    }

    file_put_contents($key, json_encode($data), LOCK_EX);
})();

$barcode = trim($_GET['barcode'] ?? '');

if (empty($barcode)) {
    echo json_encode(['error' => 'Code barre manquant']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock,
           Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton
    FROM Produit
    WHERE Id_Entreprise = ?
      AND (Code_Barre_Unite = ? OR Code_Barre_Carton = ?)
    LIMIT 1
");
$stmt->execute([$entreprise_id, $barcode, $barcode]);
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    echo json_encode(['found' => false, 'message' => 'Produit introuvable pour ce code barre']);
    exit;
}

$is_carton = ($produit['Code_Barre_Carton'] === $barcode);

echo json_encode([
    'found'              => true,
    'id'                 => $produit['Id_Produit'],
    'nom'                => $produit['Nom_Produit'],
    'prix'               => $produit['Prix_Unitaire_Produit'],
    'stock'              => $produit['Quantite_En_Stock'],
    'is_carton'          => $is_carton,
    'quantite_par_carton'=> (int) ($produit['Quantite_Par_Carton'] ?? 1),
]);
