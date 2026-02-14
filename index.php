<?php
session_start();

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

// Sinon, rediriger vers la page de connexion
header('Location: pages/login.php');
exit();
