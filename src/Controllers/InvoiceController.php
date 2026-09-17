<?php

namespace App\Controllers;

use App\Application\Billing\InvoiceService;
use App\Infrastructure\Persistence\InvoiceRepository;

/** Contrôleur de pages/invoice_add.php — création d'une vente directe (panier + scanner). */
class InvoiceController extends Controller
{
    private InvoiceService $invoices;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->invoices = new InvoiceService($pdo, new InvoiceRepository($pdo));
    }

    public function add(): void
    {
        exigerPermission(peutCreerVente());

        $entreprise_id = $_SESSION['entreprise_id'];
        $nom_vendeur = trim($_SESSION['username'] ?? '');

        $produits = $this->invoices->availableProducts((int) $entreprise_id);

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $client = trim($_POST['client'] ?? '');
            $items = $_POST['items'] ?? [];

            $mode_remise = in_array($_POST['mode_remise'] ?? '', ['livraison', 'retrait']) ? $_POST['mode_remise'] : 'livraison';

            if (empty($items)) {
                $error = 'Veuillez ajouter au moins un produit.';
            } else {
                try {
                    $numero = $this->invoices->createDirectSale($client, $nom_vendeur, $items, (int) $entreprise_id, (int) ($_SESSION['user_id'] ?? 0));
                    $this->redirect('vente_workflow.php?ref=' . urlencode($numero) . '&etape=1&mode=' . $mode_remise);
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->render('invoice_add/index', [
            'nom_vendeur'  => $nom_vendeur,
            'produits'     => $produits,
            'error'        => $error,
            'success'      => $success,
        ], 'Nouvelle Vente / Facture');
    }
}
