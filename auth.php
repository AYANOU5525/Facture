<?php
session_start();
require_once 'bdd.php';

if (!isset($_SESSION['username'])) {
    header('Location: connexion.php');
    exit();
}

// Si l'entreprise_id est manquant dans la session (ex: après migration), on le récupère
if (!isset($_SESSION['entreprise_id'])) {
    $stmt = $pdo->prepare('SELECT id, entreprise_id, role FROM utilisateurs WHERE nom_utilisateur = :username');
    $stmt->execute(['username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['entreprise_id'] = $user['entreprise_id'];
        $_SESSION['role'] = $user['role'];
    } else {
        // Utilisateur introuvable, par sécurité on déconnecte
        session_destroy();
        header('Location: connexion.php');
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];
$entreprise_id = $_SESSION['entreprise_id'];
