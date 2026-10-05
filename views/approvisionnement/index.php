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
            <!-- SCANNER CODE BARRE (carton ou unité) -->
            <div class="scanner-box mb-3" style="background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-left: 4px solid var(--bs-primary); padding: 14px; border-radius: 8px;">
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <i class="fas fa-barcode text-primary flex-shrink-0" style="font-size:1.3rem;"></i>
                    <input type="text" id="appro_barcode_input" class="form-control" style="max-width:260px; flex:1;"
                           placeholder="Scanner ou saisir le code du carton/unité..." autocomplete="off">
                    <button type="button" id="appro-btn-lookup-barcode" class="btn btn-outline-secondary btn-sm" onclick="approLookupBarcode()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <span id="appro_barcode_feedback" class="d-block small mt-2"></span>
                <p class="small text-body-secondary mt-2 mb-0">
                    <i class="fas fa-circle-info"></i> Un produit scanné (carton ou unité) est ajouté ou incrémenté automatiquement ci-dessous.
                </p>
            </div>

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

        function addApproItem(selectedId = null, qteCarton = 0, qteUnite = 1) {
            const container = document.getElementById('appro-items-container');
            const empty = document.getElementById('appro-empty-items');
            if (empty) empty.style.display = 'none';

            const div = document.createElement('div');
            div.className = 'appro-manuel-row';
            div.setAttribute('data-produit-id', selectedId ?? '');

            div.innerHTML = `
                <select name="items[${approItemCount}][produit]" class="form-control form-control-sm" required
                        onchange="this.closest('.appro-manuel-row').dataset.produitId = this.value; updateApproSubmitState()">
                    ${approRenderOptions(selectedId)}
                </select>
                <input type="number" name="items[${approItemCount}][qte_carton]" class="form-control form-control-sm"
                       min="0" value="${qteCarton}" placeholder="Cartons" oninput="updateApproSubmitState()">
                <input type="number" name="items[${approItemCount}][qte_unite]" class="form-control form-control-sm"
                       min="0" value="${qteUnite}" placeholder="Unités" oninput="updateApproSubmitState()">
                <button type="button" onclick="removeApproItem(this)" class="btn btn-danger btn-sm" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(div);
            approItemCount++;
            updateApproSubmitState();
            return div;
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

        /* ── SCAN CODE-BARRE (carton ou unité) ── réutilise le même point de résolution que la vente
           (api/lookup_product.php) : le serveur seul décide du conditionnement et du coefficient. */
        function approLookupBarcode() {
            const input    = document.getElementById('appro_barcode_input');
            const feedback = document.getElementById('appro_barcode_feedback');
            const btn      = document.getElementById('appro-btn-lookup-barcode');
            const barcode  = input.value.trim();
            if (!barcode) return;

            feedback.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Recherche...';
            feedback.style.color = '#666';
            btn.disabled = true;

            fetch('../api/lookup_product.php?barcode=' + encodeURIComponent(barcode))
                .then(r => r.json())
                .then(data => {
                    if (!data.found) {
                        feedback.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i>' + approEscHtml(data.message || 'Produit introuvable')
                            + ' — <a href="#" onclick="openApproAssociateModal(' + JSON.stringify(barcode) + '); return false;">Associer ce code à un produit existant</a>';
                        feedback.style.color = 'var(--bs-danger)';
                        return;
                    }
                    applyApproScannedProduct(data);
                    input.value = '';
                    input.focus();
                })
                .catch(() => {
                    feedback.textContent = 'Erreur de connexion';
                    feedback.style.color = 'var(--bs-danger)';
                })
                .finally(() => { btn.disabled = false; });
        }

        // Même logique de fusion que la vente (applyScannedProduct dans invoice_add) : un produit
        // déjà présent dans la liste voit sa quantité incrémentée au lieu de dupliquer une ligne.
        function applyApproScannedProduct(data) {
            const feedback = document.getElementById('appro_barcode_feedback');
            const isCarton = data.type_conditionnement === 'carton';

            const existingRow = document.querySelector(`.appro-manuel-row[data-produit-id="${data.id_produit}"]`);

            if (existingRow) {
                const targetInput = existingRow.querySelector(isCarton ? '[name*="[qte_carton]"]' : '[name*="[qte_unite]"]');
                targetInput.value = (parseInt(targetInput.value) || 0) + 1;
                updateApproSubmitState();
                setIconText(feedback, 'fa-circle-check', 'Quantité mise à jour : ' + data.nom_produit);
            } else {
                addApproItem(data.id_produit, isCarton ? 1 : 0, isCarton ? 0 : 1);
                setIconText(feedback, 'fa-circle-check', 'Ajouté : ' + data.nom_produit + (isCarton
                    ? ` — conditionnement Carton (1 = ${data.coefficient} unités)`
                    : ' — conditionnement Unité'));
            }
            feedback.style.color = 'var(--bs-success)';
        }

        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('appro_barcode_input').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); approLookupBarcode(); }
            });
        });

        /* ── ASSOCIATION D'UN CODE-BARRES INCONNU À UN PRODUIT EXISTANT ──
           Même règle qu'à la vente : jamais de correspondance automatique, toujours une confirmation
           explicite de l'utilisateur avant d'enregistrer le code sur la fiche produit. */
        let approAssociateCsrfToken = <?= json_encode(jetonCsrf()) ?>;

        function openApproAssociateModal(barcode) {
            document.getElementById('appro_associate_barcode_value').textContent = barcode;
            document.getElementById('appro_associate_barcode_select').innerHTML = approRenderOptions(null);
            document.getElementById('appro_associate_barcode_type').value = 'carton';
            document.getElementById('appro_associate_barcode_error').textContent = '';
            document.getElementById('approAssociateModal').style.display = 'flex';
        }

        function closeApproAssociateModal() {
            document.getElementById('approAssociateModal').style.display = 'none';
        }

        function submitApproAssociateBarcode() {
            const barcode = document.getElementById('appro_associate_barcode_value').textContent;
            const idProduit = document.getElementById('appro_associate_barcode_select').value;
            const type = document.getElementById('appro_associate_barcode_type').value;
            const errorEl = document.getElementById('appro_associate_barcode_error');
            const btn = document.getElementById('appro_associate_barcode_submit_btn');

            if (!idProduit) {
                errorEl.textContent = 'Sélectionnez un produit.';
                return;
            }

            const body = new URLSearchParams({
                id_produit: idProduit,
                type: type,
                code: barcode,
                csrf_token: approAssociateCsrfToken,
            });

            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Association…';

            fetch('../api/associate_barcode.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        errorEl.textContent = data.message || "Échec de l'association.";
                        return;
                    }
                    closeApproAssociateModal();
                    applyApproScannedProduct(data);
                    document.getElementById('appro_barcode_input').value = '';
                    document.getElementById('appro_barcode_input').focus();
                })
                .catch(() => { errorEl.textContent = 'Erreur de connexion.'; })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        }
    </script>

    <!-- MODAL ASSOCIATION D'UN CODE-BARRES INCONNU (réception) -->
    <div id="approAssociateModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
         z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
        <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:380px; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
                <span style="color:#fff; font-weight:600; font-size:1rem;">
                    <i class="fas fa-link" style="color:#17a2b8; margin-right:8px;"></i>Associer ce code
                </span>
                <button onclick="closeApproAssociateModal()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding:20px;">
                <p style="color:#ddd; font-size:0.88rem; margin:0 0 12px;">
                    Code scanné : <code id="appro_associate_barcode_value" style="color:#17a2b8;"></code> — inconnu du catalogue.
                    À quel produit correspond-il ?
                </p>
                <label class="form-label small" style="color:#aaa;">Produit</label>
                <select id="appro_associate_barcode_select" class="form-control form-control-sm mb-3"></select>
                <label class="form-label small" style="color:#aaa;">Ce code correspond à</label>
                <select id="appro_associate_barcode_type" class="form-control form-control-sm mb-3">
                    <option value="carton">Un carton</option>
                    <option value="unite">Une unité</option>
                </select>
                <div id="appro_associate_barcode_error" class="small text-danger mb-2"></div>
                <button type="button" id="appro_associate_barcode_submit_btn" class="btn btn-primary w-100" onclick="submitApproAssociateBarcode()">
                    <i class="fas fa-check"></i> Associer et ajouter à la réception
                </button>
            </div>
        </div>
    </div>

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
                        <tbody data-paginate="5">
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
