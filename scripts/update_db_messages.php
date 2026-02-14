<?php
require_once __DIR__ . '/../config/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS Message (
        Id_Message INT AUTO_INCREMENT PRIMARY KEY,
        Id_Expediteur INT NOT NULL,
        Id_Destinataire INT NOT NULL,
        Contenu TEXT NOT NULL,
        Date_Envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
        Lu BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (Id_Expediteur) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE,
        FOREIGN KEY (Id_Destinataire) REFERENCES Entreprise(Id_Entreprise) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";

    $pdo->exec($sql);
    echo "<h1 style='color:green'>✅ Système de messagerie activé !</h1>";
    echo "<p>Table 'Message' créée avec succès.</p>";
    echo "<a href='../dashboard.php'>Retour au tableau de bord</a>";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
