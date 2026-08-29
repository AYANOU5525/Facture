<?php
/**
 * api/scan_session.php — Scanner mobile distant (session de scan temporaire PC <-> téléphone)
 *
 * includes/auth.php n'est PAS chargé inconditionnellement ici : les actions "join"/"scan"
 * (téléphone, sans login) n'utilisent pas de session ni de CSRF. Seules les actions PC
 * (create/revoke/poll) ont besoin de la connexion — includes/auth.php doit alors rester le
 * premier fichier à toucher la session pour pouvoir en configurer les options.
 */

require_once '../config/db.php';
require_once '../vendor/autoload.php';

use App\Controllers\ScanSessionController;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (in_array($action, ['create', 'revoke', 'poll'], true)) {
    require_once '../includes/auth.php'; // session_start, garde de login, $entreprise_id
}

(new ScanSessionController($pdo))->handle($action);
