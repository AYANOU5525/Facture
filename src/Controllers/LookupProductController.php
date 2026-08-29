<?php

namespace App\Controllers;

use App\Application\Inventory\ProductLookupService;

/** Contrôleur de api/lookup_product.php — résolution d'un code-barre en fiche produit (scanner). */
class LookupProductController extends Controller
{
    public function lookup(): void
    {
        if (!peutVendre()) {
            $this->jsonResponse(['error' => 'Accès refusé.'], 403);
        }

        $this->rateLimit();

        $barcode = trim($_GET['barcode'] ?? '');

        if (empty($barcode)) {
            $this->jsonResponse(['error' => 'Code barre manquant']);
        }

        $entreprise_id = $_SESSION['entreprise_id'];
        $result = ProductLookupService::findByBarcode($this->pdo, $barcode, (int) $entreprise_id);

        if ($result === null) {
            $this->jsonResponse(['found' => false, 'message' => 'Produit introuvable pour ce code barre']);
        }

        $this->jsonResponse(['found' => true] + $result);
    }

    /** Rate limiting : 60 requêtes par minute par IP. */
    private function rateLimit(): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = sys_get_temp_dir() . '/factupro_rate_' . md5($ip) . '.json';
        $now = date('YmdHi'); // fenêtre d'une minute

        $data = file_exists($key) ? json_decode(@file_get_contents($key), true) : null;

        if (is_array($data) && ($data['window'] ?? '') === $now) {
            if (($data['count'] ?? 0) >= 60) {
                $this->jsonResponse(['error' => 'Trop de requêtes. Réessayez dans une minute.'], 429);
            }
            $data['count']++;
        } else {
            $data = ['window' => $now, 'count' => 1];
        }

        file_put_contents($key, json_encode($data), LOCK_EX);
    }
}
