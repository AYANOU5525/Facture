<?php
session_start();

require_once 'bdd.php';

if (!isset($_SESSION['username'])) {
    header('Location: connexion.php');
    exit();
}

$username = $_SESSION['username'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id > 0) {
    // Check if the product belongs to the current enterprise
    $stmt = $pdo->prepare('SELECT id FROM produits WHERE id = :id AND entreprise_id = :ent_id');
    $stmt->execute(['id' => $product_id, 'ent_id' => $_SESSION['entreprise_id']]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($produit) {
        $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id AND entreprise_id = :ent_id');
        $stmt->execute(['id' => $product_id, 'ent_id' => $_SESSION['entreprise_id']]);
    }
}

header('Location: liste_produits.php');
exit();
