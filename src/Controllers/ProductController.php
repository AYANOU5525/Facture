<?php

namespace App\Controllers;

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\ProductRepository;

/** Contrôleur de pages/products.php — catalogue produits, déstockage B2B. */
class ProductController extends Controller
{
    private ProductService $products;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->products = new ProductService(new ProductRepository($pdo));
    }

    public function index(): void
    {
        // livreur et admin plateforme : pas d'accès aux produits
        exigerPermission(peutVoirProduits());

        $readonly = !peutGererStock(); // vendeur = lecture seule

        // Récupérer l'ID de l'entreprise de l'utilisateur connecté
        $entreprise_id = $_SESSION['entreprise_id'] ?? null;
        if (!$entreprise_id) {
            $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->redirect('../includes/logout.php');
            }
            $entreprise_id = $user['Id_Entreprise'];
        }

        $success = '';
        $error = '';
        $edit_mode = false;
        $product_data = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$success, $error] = $this->handlePost($readonly, (int) $entreprise_id);
        }

        // === GESTION DE L'AFFICHAGE POUR ÉDITION ===
        if (isset($_GET['edit'])) {
            $product_data = $this->products->find((int) $_GET['edit'], (int) $entreprise_id);
            if ($product_data) {
                $edit_mode = true;
            }
        }

        // === RÉCUPÉRATION DE LA LISTE DES PRODUITS ===
        $produits = $this->products->list((int) $entreprise_id);

        // Statistiques sur le stock
        $total_produits = count($produits);
        $total_stock_val = 0;
        $produits_alerte = 0;
        foreach ($produits as $p) {
            $total_stock_val += $p['Quantite_En_Stock'] * $p['Prix_Unitaire_Produit'];
            if ((int) $p['Quantite_En_Stock'] <= (int) ($p['Seuil_Alerte_Stock'] ?? 5)) {
                $produits_alerte++;
            }
        }

        $this->render('products/index', [
            'readonly'         => $readonly,
            'success'          => $success,
            'error'            => $error,
            'edit_mode'        => $edit_mode,
            'product_data'     => $product_data,
            'produits'         => $produits,
            'total_produits'   => $total_produits,
            'total_stock_val'  => $total_stock_val,
            'produits_alerte'  => $produits_alerte,
        ], 'Gestion des Produits');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handlePost(bool $readonly, int $entreprise_id): array
    {
        exigerCsrf();

        if ($readonly) {
            return ['', "Accès refusé : vous n'avez pas la permission de modifier les produits."];
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            return $this->handleDelete($entreprise_id);
        }

        if ($action === 'toggle_b2b') {
            return $this->handleToggleB2b($entreprise_id);
        }

        return $this->handleSave($entreprise_id);
    }

    private function handleDelete(int $entreprise_id): array
    {
        try {
            $this->products->delete((int) $_POST['id_produit'], $entreprise_id);
            return ['Produit supprimé avec succès.', ''];
        } catch (\Throwable $e) {
            return ['', 'Erreur lors de la suppression : ' . $e->getMessage()];
        }
    }

    private function handleToggleB2b(int $entreprise_id): array
    {
        $id_produit = (int) ($_POST['id_produit'] ?? 0);
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

        try {
            $this->products->toggleDestockage($id_produit, $entreprise_id, $enabled);
            return [
                $enabled ? 'Produit ajouté au déstockage B2B.' : 'Produit retiré du déstockage B2B.',
                '',
            ];
        } catch (\Throwable $e) {
            return ['', 'Erreur : ' . $e->getMessage()];
        }
    }

    private function handleSave(int $entreprise_id): array
    {
        $id_produit = $_POST['id_produit'] ?? null;

        try {
            if ($id_produit) {
                $this->products->save($_POST, $entreprise_id, (int) $id_produit);
                return ['Produit modifié avec succès.', ''];
            }

            $this->products->save($_POST, $entreprise_id);
            return ['Produit ajouté avec succès.', ''];
        } catch (\Throwable $e) {
            $verbe = $id_produit ? 'la modification' : "l'ajout";
            return ['', "Erreur lors de $verbe : " . $e->getMessage()];
        }
    }
}
