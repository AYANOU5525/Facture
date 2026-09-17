<style>
    .appro-stock-after { font-size: 0.8rem; }
    .appro-stock-after .val { font-weight: 700; color: var(--bs-success); }
    .appro-manuel-row {
        display: grid;
        grid-template-columns: 1fr 100px 100px 40px;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 8px;
        padding: 10px 12px;
    }
    @media (max-width: 576px) {
        .appro-manuel-row { grid-template-columns: 1fr 70px 70px 36px; }
    }
</style>

<div class="container fade-in py-4">
    <div class="mb-4">
        <h1 class="fs-4 fw-bold mb-1"><i class="fas fa-truck-loading text-primary me-2"></i> Entrée en stock</h1>
        <p class="text-body-secondary mb-0">Réceptionnez vos commandes B2B livrées ou ajoutez manuellement des produits à votre inventaire.</p>
    </div>

    <?php if ($error): ?> <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="alert alert-success"><?= htmlspecialchars($success) ?></div> <?php endif; ?>

    <!-- AJOUT MANUEL AU STOCK -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-box-open text-primary me-2"></i> Ajouter un produit à mon stock</h2>
            <button type="button" onclick="addApproItem()" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-plus"></i> Ajouter
            </button>
        </div>
        <div class="card-body">
            <form method="POST" id="approManuelForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="approvisionnement">

                <div class="appro-manuel-row" style="background:none; border:none; padding:0 12px; margin-bottom:4px; font-size:0.72rem; font-weight:700; color:var(--bs-secondary-color); text-transform:uppercase;">
                    <span>Produit</span>
                    <span>Cartons</span>
                    <span>Unités</span>
                    <span></span>
                </div>
                <div id="appro-items-container"></div>

                <div id="appro-empty-items" class="text-center text-body-secondary py-3">
                    <i class="fas fa-boxes fs-2 opacity-25 d-block mb-2"></i>
                    Cliquez sur « Ajouter » pour choisir un produit à approvisionner
                </div>

                <div class="text-end mt-3">
                    <button type="submit" id="appro-submit-btn" class="btn btn-primary" disabled>
                        <i class="fas fa-check"></i> Ajouter le produit au stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const approProducts = <?= json_encode($produits) ?>;
        let approItemCount = 0;

        function approEscHtml(str) {
            return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function approRenderOptions(selectedValue) {
            let opts = '<option value="">— Sélectionner un produit —</option>';
            approProducts.forEach(p => {
                opts += `<option value="${p.Id_Produit}" ${p.Id_Produit == selectedValue ? 'selected' : ''}>${approEscHtml(p.Nom_Produit)}</option>`;
            });
            return opts;
        }

        function addApproItem() {
            const container = document.getElementById('appro-items-container');
            const empty = document.getElementById('appro-empty-items');
            if (empty) empty.style.display = 'none';

            const div = document.createElement('div');
            div.className = 'appro-manuel-row';

            div.innerHTML = `
                <select name="items[${approItemCount}][produit]" class="form-control form-control-sm" required onchange="updateApproSubmitState()">
                    ${approRenderOptions(null)}
                </select>
                <input type="number" name="items[${approItemCount}][qte_carton]" class="form-control form-control-sm"
                       min="0" value="0" placeholder="Cartons" oninput="updateApproSubmitState()">
                <input type="number" name="items[${approItemCount}][qte_unite]" class="form-control form-control-sm"
                       min="0" value="1" placeholder="Unités" oninput="updateApproSubmitState()">
                <button type="button" onclick="removeApproItem(this)" class="btn btn-danger btn-sm" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(div);
            approItemCount++;
            updateApproSubmitState();
        }

        function removeApproItem(btn) {
            btn.closest('.appro-manuel-row').remove();
            const rows = document.querySelectorAll('#appro-items-container .appro-manuel-row');
            if (rows.length === 0) {
                const empty = document.getElementById('appro-empty-items');
                if (empty) empty.style.display = '';
            }
            updateApproSubmitState();
        }

        function updateApproSubmitState() {
            const rows = document.querySelectorAll('#appro-items-container .appro-manuel-row');
            document.getElementById('appro-submit-btn').disabled = rows.length === 0;
        }
    </script>

    <?php if (!empty($receptions_b2b)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="h6 mb-1"><i class="fas fa-boxes-stacked text-primary me-2"></i> Réceptions à traiter</h2>
                <p class="text-body-secondary small mb-0">Indiquez la quantité reçue pour chaque ligne — le stock n'est mis à jour qu'après votre confirmation ci-dessous.</p>
            </div>
            <form method="POST" id="approForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="recevoir_b2b">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Commande</th>
                                <th>Vendeur</th>
                                <th>Produit</th>
                                <th class="text-center">Restant à recevoir</th>
                                <th class="text-center">Stock actuel</th>
                                <th style="min-width:180px;">Quantité reçue</th>
                                <th class="text-center">Déstockage B2B</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receptions_b2b as $reception):
                                $stockActuel = $reception['Stock_Actuel'] !== null ? (int) $reception['Stock_Actuel'] : null;
                            ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($reception['Numero_Commande']) ?></code></td>
                                    <td><?= htmlspecialchars($reception['Nom_Vendeur']) ?></td>
                                    <td><?= htmlspecialchars($reception['Nom_Produit']) ?></td>
                                    <td class="text-center"><?= (int) $reception['Quantite_Restante'] ?></td>
                                    <td class="text-center text-body-secondary"><?= $stockActuel !== null ? $stockActuel : '—' ?></td>
                                    <td>
                                        <input type="number" name="receptions[<?= (int) $reception['Id_Ligne'] ?>]" class="form-control form-control-sm appro-qte-input" min="0" max="<?= (int) $reception['Quantite_Restante'] ?>" value="0"
                                            data-stock-actuel="<?= $stockActuel !== null ? $stockActuel : 0 ?>"
                                            oninput="updateStockApres(this)"
                                            aria-label="Quantité reçue pour <?= htmlspecialchars($reception['Nom_Produit']) ?>">
                                        <div class="appro-stock-after text-body-secondary mt-1">
                                            Stock après : <span class="val"><?= $stockActuel !== null ? $stockActuel : '—' ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="destockage[<?= (int) $reception['Id_Ligne'] ?>]" value="1"
                                            class="form-check-input"
                                            style="width:20px; height:20px; cursor:pointer;"
                                            title="Mettre ce produit en déstockage B2B après réception"
                                            aria-label="Mettre <?= htmlspecialchars($reception['Nom_Produit']) ?> en déstockage B2B"
                                            onchange="toggleDestockageFields(this)">
                                        <div class="destockage-fields text-start mt-2" style="display:none;">
                                            <label class="form-label small text-body-secondary mb-1">Prix unitaire B2B (FCFA)</label>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm mb-2"
                                                name="prix_b2b[<?= (int) $reception['Id_Ligne'] ?>]" placeholder="Laisser vide = prix normal">
                                            <label class="form-label small text-body-secondary mb-1">Quantité min. d'achat</label>
                                            <input type="number" min="1" class="form-control form-control-sm"
                                                name="qte_min_b2b[<?= (int) $reception['Id_Ligne'] ?>]" placeholder="1">
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex gap-2 justify-content-end">
                    <a href="products.php" class="btn btn-outline-secondary">Annuler</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirmer l'entrée en stock</button>
                </div>
            </form>
        </div>

        <script>
            function toggleDestockageFields(checkbox) {
                const fields = checkbox.closest('td').querySelector('.destockage-fields');
                if (fields) fields.style.display = checkbox.checked ? 'block' : 'none';
            }

            function updateStockApres(input) {
                const actuel = parseInt(input.dataset.stockActuel, 10) || 0;
                const ajout  = parseInt(input.value, 10) || 0;
                const out    = input.parentElement.querySelector('.appro-stock-after .val');
                if (out) out.textContent = actuel + ajout;
            }
        </script>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center text-body-secondary py-5">
                <i class="fas fa-boxes-stacked fs-1 d-block mb-3 opacity-50"></i>
                <p class="mb-2">Aucune réception B2B en attente.</p>
                <a href="products.php" class="btn btn-outline-secondary btn-sm">Retour aux Stocks</a>
            </div>
        </div>
    <?php endif; ?>

</div>

</body>

</html>
