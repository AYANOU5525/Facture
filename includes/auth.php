<?php
// 1. Configuration des cookies de session (Expiration à la fermeture du navigateur)
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/error_handler.php';

// 2. En-têtes de sécurité HTTP
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; " .
    "img-src 'self' data:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none'"
);

// 2. Empêcher la mise en cache par le navigateur (Sécurité du bouton "Retour")
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../config/db.php';

// 3. Gestion du Timeout d'inactivité ( 5 minutes = 300 secondes)
$inactivity_limit = 900;

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
        // La session a expiré pour inactivité
        session_unset();
        session_destroy();
        header('Location: login.php?reason=timeout');
        exit();
    }
    // Si la page chargée n'est pas un appel de polling AJAX (ex: chat B2B), on met à jour le chrono
    if (!isset($is_ajax_polling) || !$is_ajax_polling) {
        $_SESSION['last_activity'] = time();
    }
}

// 4. Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// 5. Garantir la présence de entreprise_id dans la session
if (!isset($_SESSION['entreprise_id'])) {
    $stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        $_SESSION['entreprise_id'] = $user_data['Id_Entreprise'];
    } else {
        // Session corrompue, forcer déconnexion
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

$entreprise_id = $_SESSION['entreprise_id'];

