<?php

namespace App\Controllers;

/** Contrôleur de pages/settings.php — paramètres entreprise + mot de passe. */
class SettingsController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutGererParametres());

        $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id = $stmt->fetchColumn();

        $success = '';
        $error   = '';
        $success_pwd = '';
        $error_pwd   = '';

        $stmt_hash = $this->pdo->prepare("SELECT Mot_De_Passe_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt_hash->execute([$_SESSION['user_id']]);
        $current_hash = $stmt_hash->fetchColumn();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();

            if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
                [$success_pwd, $error_pwd] = $this->handleChangePassword($current_hash);
            } else {
                [$success, $error] = $this->handleUpdateEntreprise((int) $entreprise_id);
            }
        }

        $stmt = $this->pdo->prepare("SELECT Nom_Entreprise, Email_Entreprise, Tel_Entreprise, Adresse_Entreprise, NIF_Entreprise, Description_Entreprise, Ville, Region, Latitude, Longitude FROM Entreprise WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $ent = $stmt->fetch();

        $this->render('settings/index', [
            'success'      => $success,
            'error'        => $error,
            'success_pwd'  => $success_pwd,
            'error_pwd'    => $error_pwd,
            'ent'          => $ent,
        ], "Paramètres de l'entreprise");
    }

    /** @return array{0:string,1:string} [$success_pwd, $error_pwd] */
    private function handleChangePassword(string $current_hash): array
    {
        $old_password     = $_POST['old_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!password_verify($old_password, $current_hash)) {
            return ['', 'Mot de passe actuel incorrect.'];
        }
        if (strlen($new_password) < 8) {
            return ['', 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
        }
        if ($new_password !== $confirm_password) {
            return ['', 'Les nouveaux mots de passe ne correspondent pas.'];
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $this->pdo->prepare("UPDATE Utilisateur SET Mot_De_Passe_Utilisateur = ? WHERE Id_Utilisateur = ?")
            ->execute([$new_hash, $_SESSION['user_id']]);

        return ['Mot de passe modifié avec succès !', ''];
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleUpdateEntreprise(int $entreprise_id): array
    {
        $nom     = $_POST['nom']    ?? '';
        $adresse = $_POST['adresse'] ?? '';
        $tel     = $_POST['tel']    ?? '';
        $email   = $_POST['email']  ?? '';
        $nif     = $_POST['nif']    ?? '';
        $intro   = $_POST['description'] ?? '';
        $ville   = $_POST['ville']  ?? null;
        $region  = $_POST['region'] ?? null;
        $lat     = !empty($_POST['latitude'])  ? floatval($_POST['latitude'])  : null;
        $lon     = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE Entreprise
                SET Nom_Entreprise = ?, Adresse_Entreprise = ?, Tel_Entreprise = ?,
                    Email_Entreprise = ?, NIF_Entreprise = ?, Description_Entreprise = ?,
                    Ville = ?, Region = ?, Latitude = ?, Longitude = ?
                WHERE Id_Entreprise = ?
            ");
            $stmt->execute([$nom, $adresse, $tel, $email, $nif, $intro, $ville, $region, $lat, $lon, $entreprise_id]);
            return ['Informations mises à jour avec succès !', ''];
        } catch (\PDOException $e) {
            return ['', 'Erreur : ' . $e->getMessage()];
        }
    }
}
