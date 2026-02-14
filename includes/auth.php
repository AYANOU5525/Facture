<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['entreprise_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Garantir la présence de entreprise_id dans la session
if (!isset($_SESSION['entreprise_id'])) {
    $stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        $_SESSION['entreprise_id'] = $user_data['Id_Entreprise'];
    } else {
        // Session corrompue, forcer déconnexion
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

$entreprise_id = $_SESSION['entreprise_id'];

// Fonction pour générer un token CSRF
function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verify_csrf($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
