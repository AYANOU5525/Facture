<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Nouvelle Vente / Facture';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Nom_Utilisateur, Prenom_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$nom_vendeur = trim(($user['Prenom_Utilisateur'] ?? '') . ' ' . ($user['Nom_Utilisateur'] ?? ''));

$stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock FROM Produit WHERE Id_Entreprise = ? AND Quantite_En_Stock > 0 ORDER BY Nom_Produit");
$stmt->execute([$entreprise_id]);
$produits = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client = trim($_POST['client'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($items)) {
        $error = 'Veuillez ajouter au moins un produit.';
    } else {
        $total_vente = 0;
        $articles_json = [];
        $pdo->beginTransaction();

        try {
            $produits_deja_pris = [];

            foreach ($items as $item) {
                if (empty($item['produit']) || empty($item['quantite'])) continue;

                $id_produit = intval($item['produit']);
                $quantite = intval($item['quantite']);

                if (in_array($id_produit, $produits_deja_pris)) {
                    throw new Exception("Le produit ID $id_produit est présent plusieurs fois. Veuillez regrouper.");
                }
                $produits_deja_pris[] = $id_produit;

                $stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock FROM Produit WHERE Id_Produit = ? AND Id_Entreprise = ? FOR UPDATE");
                $stmt->execute([$id_produit, $entreprise_id]);
                $produit = $stmt->fetch();

                if (!$produit || $produit['Quantite_En_Stock'] < $quantite) {
                    throw new Exception("Stock insuffisant pour " . ($produit['Nom_Produit'] ?? 'produit'));
                }

                $prix_unitaire = $produit['Prix_Unitaire_Produit'];
                $total_vente += ($prix_unitaire * $quantite);

                $articles_json[] = [
                    'id_produit' => $id_produit,
                    'nom' => $produit['Nom_Produit'],
                    'quantite' => $quantite,
                    'prix' => $prix_unitaire,
                    'total' => ($prix_unitaire * $quantite)
                ];

                $nouveau_stock = $produit['Quantite_En_Stock'] - $quantite;
                $pdo->prepare("UPDATE Produit SET Quantite_En_Stock = ? WHERE Id_Produit = ?")->execute([$nouveau_stock, $id_produit]);
            }

            if (empty($articles_json)) throw new Exception("Aucun article valide.");

            $numero = 'FAC-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO Vente (Numero_Vente, Nom_Client, Nom_Vendeur, Articles_JSON, Montant_Total, Type_Vente, Id_Entreprise, Date_Vente) VALUES (?, ?, ?, ?, ?, 'directe', ?, NOW())");
            $stmt->execute([$numero, $client, $nom_vendeur, json_encode($articles_json, JSON_UNESCAPED_UNICODE), $total_vente, $entreprise_id]);
            $id_vente = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO Facture (Id_Vente, Numero_Facture, Date_Echeance, Montant_HT, Montant_TTC, Id_Entreprise) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?)");
            $stmt->execute([$id_vente, $numero, $total_vente * 0.8, $total_vente, $entreprise_id]);
            $id_facture = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO Logistique (Id_Vente, Id_Facture, Statut_Livraison, Id_Entreprise) VALUES (?, ?, 'traitement', ?)");
            $stmt->execute([$id_vente, $id_facture, $entreprise_id]);

            $pdo->commit();

            header('Location: vente_workflow.php?ref=' . urlencode($numero) . '&etape=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-cart-plus"></i> Nouvelle Vente</h1>
    </div>

    <?php if ($error): ?> <div class="alert alert-danger" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div> <?php endif; ?>

    <form method="POST" id="invoiceForm" class="card">
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: bold;">Nom du client</label>
            <input type="text" name="client" class="form-control" value="Client Comptant" required>
        </div>

        <h3>Articles</h3>
        <div id="items-container">
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article
        </button>

        <div style="border-top: 1px solid #eee; padding-top: 20px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Valider la vente</button>
            <a href="sales.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<script>
    let itemCount = 0;

    const products = <?php echo json_encode($produits) ?>;

    function renderOptions(selectedValue) {
        let opts = '<option value="">Sélectionner un produit</option>';
        products.forEach(p => {
            let isSelected = (p.Id_Produit == selectedValue);
            opts += `<option value="${p.Id_Produit}" ${isSelected ? 'selected' : ''}>
                        ${p.Nom_Produit} (${p.Prix_Unitaire_Produit} F - Stock: ${p.Quantite_En_Stock})
                     </option>`;
        });
        return opts;
    }

    function addItem(selectedId = null) {
        const container = document.getElementById('items-container');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.setAttribute('data-id', itemCount);
        div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';

        div.innerHTML = `
            <select name="items[${itemCount}][produit]" class="form-control product-select" required style="flex: 3;" onchange="updateOptions()">
                ${renderOptions(selectedId)}
            </select>
            <input type="number" name="items[${itemCount}][quantite]" class="form-control" placeholder="Qté" min="1" required style="flex: 1;">
            <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(div);
        itemCount++;
        updateOptions(); 
    }

    function removeItem(btn) {
        btn.parentElement.remove();
        updateOptions();
    }

  
    function updateOptions() {
        const allSelects = document.querySelectorAll('.product-select');
        const selectedValues = [];
        allSelects.forEach(select => {
            if (select.value) selectedValues.push(select.value);
        });

        allSelects.forEach(select => {
            const myValue = select.value; 

            Array.from(select.options).forEach(option => {
                if (!option.value) return;

                if (selectedValues.includes(option.value)) {
                    if (option.value !== myValue) {
                        option.disabled = true;
                        option.text = option.text.replace(' (Déjà sélectionné)', '') + ' (Déjà sélectionné)';
                    } else {
                        option.disabled = false;
                        option.text = option.text.replace(' (Déjà sélectionné)', '');
                    }
                } else {
                    option.disabled = false;
                    option.text = option.text.replace(' (Déjà sélectionné)', '');
                }
            });
        });
    }

    // Initialiser avec une ligne
    window.onload = function() {
        addItem();
    };
</script>

</body>

</html>
