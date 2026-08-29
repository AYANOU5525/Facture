<?php

namespace App\Controllers;

use App\Application\Inventory\StockService;
use App\Infrastructure\Persistence\StockRepository;

/** Contrôleur de pages/approvisionnement.php — réception des commandes B2B livrées. */
class StockController extends Controller
{
    private StockService $stock;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->stock = new StockService($pdo, new StockRepository($pdo));
    }

    public function index(): void
    {
        exigerPermission(peutGererStock());

        $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id = $stmt->fetchColumn();

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $action = $_POST['action'] ?? 'approvisionnement';

            if ($action === 'recevoir_b2b') {
                [$success, $error] = $this->handleRecevoirB2b((int) $entreprise_id);
            }
        }

        $receptions_b2b = $this->fetchReceptionsEnAttente($entreprise_id);

        $this->render('approvisionnement/index', [
            'error'           => $error,
            'success'         => $success,
            'receptions_b2b'  => $receptions_b2b,
        ], 'Approvisionnement');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleRecevoirB2b(int $entreprise_id): array
    {
        $receptions = $_POST['receptions'] ?? [];
        $quantites_recues = 0;

        try {
            $this->pdo->beginTransaction();

            $destockage = $_POST['destockage'] ?? [];

            foreach ($receptions as $id_ligne => $quantite) {
                $quantite = max(0, (int) $quantite);
                if ($quantite === 0) {
                    continue;
                }

                $stmt = $this->pdo->prepare("
                    SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Receptionnee,
                           p.Description_Produit, p.Prix_Unitaire_Produit, p.Prix_B2B,
                           p.Code_Barre_Unite, p.Code_Barre_Carton, p.Quantite_Par_Carton
                    FROM Ligne_Commande_B2B l
                    JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
                    JOIN Produit p ON p.Id_Produit = l.Id_Produit
                    WHERE l.Id_Ligne = ?
                      AND c.Id_Entreprise_Acheteuse = ?
                      AND c.Statut = 'livree'
                    FOR UPDATE
                ");
                $stmt->execute([(int) $id_ligne, $entreprise_id]);
                $ligne = $stmt->fetch();

                if (!$ligne) {
                    throw new \RuntimeException('Ligne de réception introuvable ou non autorisée.');
                }

                $enable_destockage = isset($destockage[$id_ligne]) && $destockage[$id_ligne] === '1';

                $prix_b2b_saisi = null;
                $qte_min_b2b_saisie = null;
                if ($enable_destockage) {
                    $prix_saisi = filter_var($_POST['prix_b2b'][$id_ligne] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($prix_saisi !== false && $prix_saisi > 0) {
                        $prix_b2b_saisi = $prix_saisi;
                    }
                    $qte_min_saisie = filter_var($_POST['qte_min_b2b'][$id_ligne] ?? null, FILTER_VALIDATE_INT);
                    if ($qte_min_saisie !== false && $qte_min_saisie > 0) {
                        $qte_min_b2b_saisie = $qte_min_saisie;
                    }
                }

                $this->stock->receiveB2BLine($ligne, $quantite, $entreprise_id, $enable_destockage, $prix_b2b_saisi, $qte_min_b2b_saisie);
                $this->pdo->prepare("UPDATE Ligne_Commande_B2B SET Quantite_Receptionnee = Quantite_Receptionnee + ? WHERE Id_Ligne = ?")
                    ->execute([$quantite, $ligne['Id_Ligne']]);
                $quantites_recues += $quantite;
            }

            $this->pdo->commit();
            $success = $quantites_recues > 0
                ? "$quantites_recues unité(s) B2B ajoutée(s) au stock."
                : 'Aucune quantité B2B sélectionnée.';

            return [$success, ''];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', 'Erreur lors de la réception B2B : ' . $e->getMessage()];
        }
    }

    private function fetchReceptionsEnAttente(int $entreprise_id): array
    {
        // Jointure (facultative) vers Produit pour afficher le stock actuel et permettre
        // à la vue de calculer "stock après réception" avant validation — pur affichage,
        // la transaction de réception elle-même relit et verrouille la ligne séparément.
        $stmt = $this->pdo->prepare("
            SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Receptionnee,
                   (l.Quantite - l.Quantite_Receptionnee) AS Quantite_Restante,
                   c.Numero_Commande, e.Nom_Entreprise AS Nom_Vendeur,
                   p.Quantite_En_Stock AS Stock_Actuel
            FROM Ligne_Commande_B2B l
            JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B
            JOIN Entreprise e ON e.Id_Entreprise = c.Id_Entreprise_Vendeuse
            LEFT JOIN Produit p ON p.Id_Produit = l.Id_Produit
            WHERE c.Id_Entreprise_Acheteuse = ? AND c.Statut = 'livree'
              AND l.Quantite_Receptionnee < l.Quantite
            ORDER BY c.Date_Commande DESC, l.Id_Ligne ASC
        ");
        $stmt->execute([$entreprise_id]);
        return $stmt->fetchAll();
    }
}
