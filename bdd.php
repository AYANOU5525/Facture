<?php
$host = 'localhost';
$dbname = 'facturation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SHOW COLUMNS FROM utilisateurs LIKE 'role'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN role VARCHAR(50) DEFAULT 'utilisateur'");
        $pdo->exec("UPDATE utilisateurs SET role = 'utilisateur' WHERE role IS NULL");
    }
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>