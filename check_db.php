<?php
require 'config/db.php';
try {
    echo "Check for Vente table structure:\n";
    $stmt = $pdo->query("DESCRIBE Vente");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";

    echo "\nTesting sales.php query:\n";
    $q1 = "SELECT Id_Vente, Numero_Vente, Nom_Client, Nom_Vendeur, Date_Vente, Articles_JSON, Montant_Total, Type_Vente FROM Vente WHERE Id_Entreprise = 1 ORDER BY Date_Vente DESC";
    $pdo->prepare($q1);
    echo "SUCCESS: sales.php query prepared.\n";

    echo "\nTesting invoice_add.php insert (User check):\n";
    $q2 = "SELECT Nom_Utilisateur, Prenom_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = 1";
    $pdo->prepare($q2);
    echo "SUCCESS: User column check query prepared.\n";

    echo "\nTesting invoice_view.php query:\n";
    $q3 = "SELECT v.*, e.Nom_Entreprise, e.Adresse_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.NIF_Entreprise
           FROM Vente v
           JOIN Entreprise e ON v.Id_Entreprise = e.Id_Entreprise
           WHERE v.Numero_Vente = 'FIX' AND v.Id_Entreprise = 1";
    $pdo->prepare($q3);
    echo "SUCCESS: invoice_view.php query prepared.\n";
} catch (Exception $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
}
