<?php

namespace App\Controllers;

use App\Application\Inventory\ProductLookupService;

/**
 * Contrôleur de api/scan_session.php — scanner mobile distant (session de scan
 * temporaire PC <-> téléphone).
 *
 * Actions PC (utilisateur connecté, includes/auth.php déjà chargé par le point
 * d'entrée pour create/revoke/poll) : create, revoke, poll.
 * Actions téléphone (aucune session de login, uniquement le token du QR) : join, scan.
 *
 * Le téléphone n'envoie jamais de prix/quantité/entreprise — uniquement un code-barre.
 * Le serveur reste seul décisionnaire du produit, de son prix et de son stock.
 */
class ScanSessionController extends Controller
{
    private const TTL_SECONDS = 900; // 15 minutes

    public function handle(string $action): void
    {
        // ============================================================
        // ACTIONS PC (utilisateur connecté)
        // ============================================================
        if (in_array($action, ['create', 'revoke', 'poll'], true)) {
            if (!peutCreerVente()) {
                $this->jsonResponse(['success' => false, 'error' => 'Accès refusé.'], 403);
            }

            if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->createSession();
            }

            if ($action === 'revoke' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->revokeSession();
            }

            if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $this->pollSession();
            }

            $this->jsonResponse(['success' => false, 'error' => "Action '$action' inconnue."], 400);
        }

        // ============================================================
        // ACTIONS TÉLÉPHONE (pas de login — uniquement le token du QR)
        // ============================================================
        if ($action === 'join' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->joinSession();
        }

        if ($action === 'scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->scanBarcode();
        }

        $this->jsonResponse(['success' => false, 'error' => "Action '$action' inconnue ou méthode invalide."], 400);
    }

    private function createSession(): void
    {
        exigerCsrf();

        $user_id = $_SESSION['user_id'];
        $entreprise_id = $_SESSION['entreprise_id'];

        // Nettoyage paresseux des sessions expirées (pas de cron dans le projet).
        $this->pdo->exec("UPDATE Scan_Session SET Statut = 'expire' WHERE Statut IN ('en_attente','connecte') AND Expires_At < NOW()");

        // Une seule session de scan active à la fois par utilisateur — on révoque les précédentes.
        $this->pdo->prepare("UPDATE Scan_Session SET Statut = 'revoque' WHERE Id_Utilisateur = ? AND Statut IN ('en_attente','connecte')")
            ->execute([$user_id]);

        // 'produit' : le serveur résout le code-barre en fiche produit (vente, approvisionnement).
        // 'texte'   : le serveur relaie le code-barre brut (ex. remplissage d'un champ code-barre).
        $mode = ($_POST['mode'] ?? 'produit') === 'texte' ? 'texte' : 'produit';

        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare("
            INSERT INTO Scan_Session (Token, Id_Utilisateur, Id_Entreprise, Statut, Mode, Created_At, Expires_At)
            VALUES (?, ?, ?, 'en_attente', ?, NOW(), DATE_ADD(NOW(), INTERVAL " . self::TTL_SECONDS . " SECOND))
        ");
        $stmt->execute([$token, $user_id, $entreprise_id, $mode]);
        $idScanSession = (int) $this->pdo->lastInsertId();
        $this->audit($user_id, $entreprise_id, 'scan_session_create', $idScanSession);

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
        $joinUrl = $scheme . '://' . $host . $appRoot . '/pages/scanner_mobile.php?token=' . urlencode($token);

        $this->jsonResponse([
            'success'         => true,
            'id_scan_session' => $idScanSession,
            'join_url'        => $joinUrl,
            'expires_in'      => self::TTL_SECONDS,
        ]);
    }

    private function revokeSession(): void
    {
        exigerCsrf();

        $user_id = $_SESSION['user_id'];
        $entreprise_id = $_SESSION['entreprise_id'];

        $id = (int) ($_POST['id_scan_session'] ?? 0);
        $stmt = $this->pdo->prepare("UPDATE Scan_Session SET Statut = 'revoque' WHERE Id_Scan_Session = ? AND Id_Utilisateur = ?");
        $stmt->execute([$id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $this->audit($user_id, $entreprise_id, 'scan_session_revoke', $id);
        }

        $this->jsonResponse(['success' => true]);
    }

    private function pollSession(): void
    {
        $user_id = $_SESSION['user_id'];
        $entreprise_id = $_SESSION['entreprise_id'];

        $id      = (int) ($_GET['id'] ?? 0);
        $sinceId = (int) ($_GET['since_id'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT * FROM Scan_Session WHERE Id_Scan_Session = ? AND Id_Utilisateur = ? LIMIT 1");
        $stmt->execute([$id, $user_id]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$session) {
            $this->jsonResponse(['success' => true, 'statut' => 'introuvable', 'scans' => []]);
        }

        if ($session['Statut'] !== 'expire' && strtotime($session['Expires_At']) < time()) {
            $this->pdo->prepare("UPDATE Scan_Session SET Statut = 'expire' WHERE Id_Scan_Session = ?")->execute([$id]);
            $session['Statut'] = 'expire';
            $this->audit($user_id, $entreprise_id, 'scan_session_expire', $id);
        }

        $scans = [];
        $stmt = $this->pdo->prepare("
            SELECT Id_Scan, Code_Barre
            FROM Scan_Session_Scan
            WHERE Id_Scan_Session = ? AND Id_Scan > ?
            ORDER BY Id_Scan ASC
        ");
        $stmt->execute([$id, $sinceId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lastId = $sinceId;
        foreach ($rows as $row) {
            $lastId = (int) $row['Id_Scan'];
            if ($session['Mode'] === 'texte') {
                $scans[] = ['id_scan' => (int) $row['Id_Scan'], 'barcode' => $row['Code_Barre']];
                continue;
            }
            $product = ProductLookupService::findByBarcode($this->pdo, $row['Code_Barre'], (int) $session['Id_Entreprise']);
            if ($product !== null) {
                $scans[] = ['id_scan' => (int) $row['Id_Scan']] + $product;
            }
        }

        $this->jsonResponse([
            'success'      => true,
            'statut'       => $session['Statut'],
            'mode'         => $session['Mode'],
            'expires_at'   => $session['Expires_At'],
            'last_scan_id' => $lastId,
            'scans'        => $scans,
        ]);
    }

    private function joinSession(): void
    {
        $this->rateLimit('join', 30);

        $token = trim($_GET['token'] ?? '');
        $providedDevice = trim($_GET['device'] ?? '');

        if ($token === '') {
            $this->jsonResponse(['success' => false, 'error' => 'invalid', 'message' => 'Session de scan invalide.']);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM Scan_Session WHERE Token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$session || $session['Statut'] === 'revoque') {
            $this->jsonResponse(['success' => false, 'error' => 'invalid', 'message' => 'Session de scan invalide.']);
        }

        if ($session['Statut'] === 'expire' || strtotime($session['Expires_At']) < time()) {
            if ($session['Statut'] !== 'expire') {
                $this->pdo->prepare("UPDATE Scan_Session SET Statut = 'expire' WHERE Id_Scan_Session = ?")->execute([$session['Id_Scan_Session']]);
            }
            $this->jsonResponse(['success' => false, 'error' => 'expired', 'message' => "Cette session de scan a expiré. Veuillez générer un nouveau QR Code."]);
        }

        if ($session['Statut'] === 'connecte') {
            $boundDevice = (string) $session['Phone_Device_Token'];
            if ($providedDevice === '' || !hash_equals($boundDevice, $providedDevice)) {
                $this->jsonResponse(['success' => false, 'error' => 'device_conflict', 'message' => 'Cette session est déjà utilisée par un autre appareil.']);
            }
            $deviceToken = $boundDevice;
            $this->pdo->prepare("UPDATE Scan_Session SET Last_Activity = NOW() WHERE Id_Scan_Session = ?")->execute([$session['Id_Scan_Session']]);
        } else {
            // Première connexion : ce téléphone devient l'appareil exclusif de la session.
            $deviceToken = bin2hex(random_bytes(16));
            $this->pdo->prepare("UPDATE Scan_Session SET Statut = 'connecte', Phone_Device_Token = ?, Last_Activity = NOW() WHERE Id_Scan_Session = ?")
                ->execute([$deviceToken, $session['Id_Scan_Session']]);
            $this->audit((int) $session['Id_Utilisateur'], (int) $session['Id_Entreprise'], 'scan_session_phone_join', (int) $session['Id_Scan_Session']);
        }

        $stmt = $this->pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
        $stmt->execute([$session['Id_Entreprise']]);
        $entreprise = (string) $stmt->fetchColumn();

        $this->jsonResponse([
            'success'      => true,
            'device_token' => $deviceToken,
            'entreprise'   => $entreprise,
            'mode'         => $session['Mode'],
            'expires_at'   => $session['Expires_At'],
            'expires_in'   => max(0, strtotime($session['Expires_At']) - time()),
        ]);
    }

    private function scanBarcode(): void
    {
        $this->rateLimit('scan', 60);

        $token   = trim($_POST['token'] ?? '');
        $device  = trim($_POST['device'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');

        if ($token === '' || $device === '') {
            $this->jsonResponse(['success' => false, 'error' => 'invalid', 'message' => 'Session de scan invalide.']);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM Scan_Session WHERE Token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$session || $session['Statut'] === 'revoque') {
            $this->jsonResponse(['success' => false, 'error' => 'invalid', 'message' => 'Session de scan invalide.']);
        }

        if ($session['Statut'] === 'expire' || strtotime($session['Expires_At']) < time()) {
            $this->jsonResponse(['success' => false, 'error' => 'expired', 'message' => "Cette session de scan a expiré. Veuillez générer un nouveau QR Code."]);
        }

        if (!hash_equals((string) $session['Phone_Device_Token'], $device)) {
            $this->jsonResponse(['success' => false, 'error' => 'device_conflict', 'message' => 'Cette session est déjà utilisée par un autre appareil.']);
        }

        if ($barcode === '') {
            $this->jsonResponse(['success' => false, 'message' => 'Code-barre non reconnu.']);
        }

        $this->pdo->prepare("UPDATE Scan_Session SET Last_Activity = NOW() WHERE Id_Scan_Session = ?")->execute([$session['Id_Scan_Session']]);

        // Anti double-scan : un même code envoyé deux fois en moins d'une seconde n'est comptabilisé qu'une fois.
        $stmt = $this->pdo->prepare("SELECT Code_Barre, Created_At FROM Scan_Session_Scan WHERE Id_Scan_Session = ? ORDER BY Id_Scan DESC LIMIT 1");
        $stmt->execute([$session['Id_Scan_Session']]);
        $last = $stmt->fetch(\PDO::FETCH_ASSOC);
        $isDuplicate = $last && $last['Code_Barre'] === $barcode && (time() - strtotime($last['Created_At'])) < 1;

        if ($session['Mode'] === 'texte') {
            // Mode relais brut : pas de résolution produit, le PC décide seul quoi faire du code.
            if (!$isDuplicate) {
                $this->pdo->prepare("INSERT INTO Scan_Session_Scan (Id_Scan_Session, Code_Barre, Id_Produit) VALUES (?, ?, NULL)")
                    ->execute([$session['Id_Scan_Session'], $barcode]);
                $this->audit((int) $session['Id_Utilisateur'], (int) $session['Id_Entreprise'], 'scan_session_scan', null, $barcode);
            }
            $this->jsonResponse(['success' => true, 'barcode' => $barcode]);
        }

        $product = ProductLookupService::findByBarcode($this->pdo, $barcode, (int) $session['Id_Entreprise']);

        if ($product === null) {
            $this->jsonResponse(['success' => false, 'message' => 'Produit introuvable pour ce code barre.']);
        }

        if (!$isDuplicate) {
            $this->pdo->prepare("INSERT INTO Scan_Session_Scan (Id_Scan_Session, Code_Barre, Id_Produit) VALUES (?, ?, ?)")
                ->execute([$session['Id_Scan_Session'], $barcode, $product['id_produit']]);
            $this->audit(
                (int) $session['Id_Utilisateur'],
                (int) $session['Id_Entreprise'],
                'scan_session_scan',
                $product['id_produit'],
                $barcode
            );
        }

        $this->jsonResponse(['success' => true] + $product);
    }

    private function rateLimit(string $bucket, int $limitPerMinute): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = sys_get_temp_dir() . '/factupro_rate_scan_' . $bucket . '_' . md5($ip) . '.json';
        $now = date('YmdHi');

        $data = file_exists($key) ? json_decode(@file_get_contents($key), true) : null;

        if (is_array($data) && ($data['window'] ?? '') === $now) {
            if (($data['count'] ?? 0) >= $limitPerMinute) {
                $this->jsonResponse(['success' => false, 'error' => 'rate_limited', 'message' => 'Trop de requêtes. Réessayez dans une minute.'], 429);
            }
            $data['count']++;
        } else {
            $data = ['window' => $now, 'count' => 1];
        }

        file_put_contents($key, json_encode($data), LOCK_EX);
    }

    /** Table Audit_Log déjà présente en base — trace les événements sensibles du scanner distant sans stocker de donnée sensible. */
    private function audit(int $userId, int $entrepriseId, string $action, ?int $idCible, ?string $details = null): void
    {
        $this->pdo->prepare("
            INSERT INTO Audit_Log (Id_Utilisateur, Id_Entreprise, Action, Table_Cible, Id_Cible, Details, IP_Address)
            VALUES (?, ?, ?, 'Scan_Session', ?, ?, ?)
        ")->execute([$userId, $entrepriseId, $action, $idCible, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
