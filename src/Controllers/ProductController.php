<?php

namespace App\Controllers;

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\CategoryRepository;
use App\Infrastructure\Persistence\ProductRepository;

/** Contrôleur de pages/products.php — catalogue produits, déstockage B2B, catégories. */
class ProductController extends Controller
{
    private ProductService $products;
    private CategoryRepository $categories;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->products = new ProductService(new ProductRepository($pdo));
        $this->categories = new CategoryRepository($pdo);
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

        // === CATÉGORIES (Ligne_Produit / Contenir) ===
        $categories = $this->categories->listByEnterprise((int) $entreprise_id);
        $categoriesParProduit = $this->categories->categoryIdsByProduct((int) $entreprise_id);
        foreach ($produits as &$p) {
            $p['Categories'] = $categoriesParProduit[(int) $p['Id_Produit']] ?? [];
        }
        unset($p);
        if ($product_data !== null) {
            $product_data['Categories'] = $categoriesParProduit[(int) $product_data['Id_Produit']] ?? [];
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
            'categories'       => $categories,
        ], 'Gestion des Produits');
    }

    /**
     * Point d'entrée api/associate_barcode.php — associe un code-barres scanné mais inconnu
     * à un produit existant, après confirmation explicite de l'utilisateur côté vente/réception.
     * Réponse JSON (même forme que LookupProductController::lookup) pour rebrancher immédiatement
     * le produit dans le panier/la réception en cours sans recharger la page.
     */
    public function associateBarcode(): void
    {
        if (!peutVendre()) {
            $this->jsonResponse(['success' => false, 'message' => 'Accès refusé.'], 403);
        }
        exigerCsrf();

        $entreprise_id = (int) ($_SESSION['entreprise_id'] ?? 0);
        $productId = (int) ($_POST['id_produit'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'unite');
        $code = trim((string) ($_POST['code'] ?? ''));

        try {
            $this->products->associateBarcode($productId, $entreprise_id, $type, $code);
            $this->audit(
                (int) ($_SESSION['user_id'] ?? 0),
                $entreprise_id,
                'product_barcode_associated',
                'Produit',
                $productId,
                "$type : $code"
            );
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        $product = \App\Application\Inventory\ProductLookupService::findByBarcode($this->pdo, $code, $entreprise_id);
        if ($product === null) {
            // Ne devrait pas arriver (on vient de l'associer) — filet de sécurité.
            $this->jsonResponse(['success' => false, 'message' => "Associé, mais impossible de relire le produit."]);
        }

        $this->jsonResponse(['success' => true, 'found' => true] + $product);
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

        if ($action === 'add_category') {
            return $this->handleAddCategory($entreprise_id);
        }

        if ($action === 'delete_category') {
            return $this->handleDeleteCategory($entreprise_id);
        }

        return $this->handleSave($entreprise_id);
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleAddCategory(int $entreprise_id): array
    {
        $libelle = trim((string) ($_POST['libelle'] ?? ''));
        if ($libelle === '') {
            return ['', 'Le nom de la catégorie ne peut pas être vide.'];
        }

        try {
            $this->categories->create($libelle, $entreprise_id);
            return ['Catégorie « ' . $libelle . ' » ajoutée.', ''];
        } catch (\Throwable $e) {
            return ['', 'Erreur lors de la création de la catégorie : ' . $e->getMessage()];
        }
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleDeleteCategory(int $entreprise_id): array
    {
        try {
            $this->categories->delete((int) ($_POST['id_ligne_produit'] ?? 0), $entreprise_id);
            return ['Catégorie supprimée.', ''];
        } catch (\Throwable $e) {
            return ['', 'Erreur lors de la suppression de la catégorie : ' . $e->getMessage()];
        }
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
        $categoryIds = array_map('intval', (array) ($_POST['categories'] ?? []));

        try {
            if ($id_produit) {
                $productId = $this->products->save($_POST, $entreprise_id, (int) $id_produit);
                $this->categories->assignToProduct($productId, $categoryIds, $entreprise_id);
                return ['Produit modifié avec succès.', ''];
            }

            $productId = $this->products->save($_POST, $entreprise_id);
            $this->categories->assignToProduct($productId, $categoryIds, $entreprise_id);
            return ['Produit ajouté avec succès.', ''];
        } catch (\Throwable $e) {
            $verbe = $id_produit ? 'la modification' : "l'ajout";
            return ['', "Erreur lors de $verbe : " . $e->getMessage()];
        }
    }
}
