<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>

<style>
.lv-wrap { max-width:640px; margin:0 auto; display:flex; flex-direction:column; gap:16px; }

/* Stepper */
.lv-stepper { display:flex; align-items:center; background:var(--bg-card); border:1px solid var(--zinc-200); border-radius:14px; padding:18px 24px; gap:0; }
.lv-step { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; }
.lv-step-dot { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:700; flex-shrink:0; border:2px solid transparent; transition:all .2s; }
.lv-step-dot.done    { background:var(--success); color:#fff; border-color:var(--success); }
.lv-step-dot.active  { background:var(--primary); color:#fff; border-color:var(--primary); box-shadow:0 0 0 4px rgba(0,70,255,.15); }
.lv-step-dot.pending { background:var(--zinc-100); color:var(--text-muted); border-color:var(--zinc-200); }
.lv-step-label { font-size:.72rem; font-weight:600; color:var(--text-muted); text-align:center; line-height:1.2; }
.lv-step-label.active  { color:var(--primary); }
.lv-step-label.done    { color:var(--success); }
.lv-connector { flex:1; height:2px; background:var(--zinc-200); margin:0 4px; margin-bottom:20px; transition:background .2s; }
.lv-connector.done { background:var(--success); }
.lv-connector.active { background:var(--primary); }

/* Main card */
.lv-card { background:var(--bg-card); border:1px solid var(--zinc-200); border-radius:14px; overflow:hidden; }
.lv-card-header { display:flex; align-items:center; gap:14px; padding:20px 24px; border-bottom:1px solid var(--zinc-100); }
.lv-dest-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.lv-dest-icon.b2b    { background:rgba(0,70,255,.1); color:var(--primary); }
.lv-dest-icon.retail { background:rgba(10,143,91,.1); color:var(--success); }
.lv-dest-name h2 { margin:0; font-size:1.1rem; }
.lv-dest-name span { font-size:.8rem; color:var(--text-muted); font-family:monospace; }
.lv-info-list { padding:0 24px; }
.lv-info-row { display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid var(--zinc-100); gap:12px; }
.lv-info-row:last-child { border-bottom:none; }
.lv-info-key { color:var(--text-muted); font-size:.85rem; display:flex; align-items:center; gap:8px; flex-shrink:0; }
.lv-info-key i { width:14px; text-align:center; }
.lv-info-val { font-weight:600; font-size:.9rem; text-align:right; }

/* Action zone */
.lv-action { padding:20px 24px; border-top:1px solid var(--zinc-100); }
.lv-action-hint { font-size:.88rem; color:var(--text-muted); margin-bottom:16px; display:flex; align-items:flex-start; gap:8px; }
.lv-btn { width:100%; padding:15px; font-size:1.05rem; font-weight:700; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:10px; border:none; cursor:pointer; transition:opacity .15s,transform .15s; }
.lv-btn:hover { opacity:.9; transform:translateY(-1px); }
.lv-done-state { text-align:center; padding:8px 0; }
.lv-done-state i { font-size:2.8rem; color:var(--success); display:block; margin-bottom:12px; }
.lv-done-state p { font-size:1rem; font-weight:600; color:var(--success); margin:0; }
.lv-done-state small { color:var(--text-muted); font-size:.82rem; }
.lv-fields-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
@media(max-width:480px) { .lv-fields-grid { grid-template-columns:1fr; } }
</style>

<div class="container fade-in py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-body-secondary mb-0">Détails et mise à jour de votre livraison en cours</p>
        <a href="logistique.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="lv-wrap">
        <!-- Stepper de progression -->
        <div class="lv-stepper">
            <?php foreach ($etapes as $n => $etape): ?>
                <?php if ($n > 0): ?>
                    <div class="lv-connector <?= $etape['etat'] === 'pending' ? '' : ($etape['etat'] === 'done' ? 'done' : 'active') ?>"></div>
                <?php endif; ?>
                <div class="lv-step">
                    <div class="lv-step-dot <?= $etape['etat'] ?>">
                        <i class="fas <?= $etape['etat'] === 'done' ? 'fa-check' : $etape['icon'] ?>"></i>
                    </div>
                    <span class="lv-step-label <?= $etape['etat'] === 'pending' ? '' : $etape['etat'] ?>"><?= htmlspecialchars($etape['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Fiche livraison -->
        <div class="lv-card">
            <div class="lv-card-header">
                <div class="lv-dest-icon <?= $log['Id_Commande_B2B'] ? 'b2b' : 'retail' ?>">
                    <i class="fas fa-<?= $log['Id_Commande_B2B'] ? 'building' : 'user' ?>"></i>
                </div>
                <div class="lv-dest-name">
                    <h2><?= htmlspecialchars($dest) ?></h2>
                    <span>Réf. <?= htmlspecialchars($ref) ?></span>
                </div>
                <span class="badge text-bg-<?= $sl['badge'] ?> ms-auto">
                    <?= htmlspecialchars($sl['label']) ?>
                </span>
            </div>

            <div class="lv-info-list">
                <div class="lv-info-row">
                    <span class="lv-info-key"><i class="fas fa-map-marker-alt" style="color:var(--danger);"></i> Adresse</span>
                    <span class="lv-info-val" style="font-size:.85rem;"><?= htmlspecialchars($log['Adresse_Livraison'] ?? $log['Adresse_Acheteur'] ?? 'Non renseignée') ?></span>
                </div>
                <div class="lv-info-row">
                    <span class="lv-info-key"><i class="fas fa-shipping-fast"></i> Transporteur</span>
                    <span class="lv-info-val"><?= htmlspecialchars($log['Transporteur'] ?? '-') ?></span>
                </div>
                <div class="lv-info-row">
                    <span class="lv-info-key"><i class="fas fa-barcode"></i> N° de suivi</span>
                    <span class="lv-info-val"><code style="font-size:.85rem;"><?= htmlspecialchars($log['Numero_Suivi'] ?? '-') ?></code></span>
                </div>
                <div class="lv-info-row">
                    <span class="lv-info-key"><i class="fas fa-calendar-alt"></i> Livraison prévue</span>
                    <span class="lv-info-val"><?= $log['Date_Livraison_Prevue'] ? date('d/m/Y', strtotime($log['Date_Livraison_Prevue'])) : '-' ?></span>
                </div>
            </div>

            <?php if ($lat_dst && $lng_dst): ?>
            <div style="padding:0 24px 20px;">
                <div id="livreur-map" style="height:200px; border-radius:10px; border:1px solid var(--zinc-200);"></div>
            </div>
            <?php endif; ?>

            <!-- Zone d'action -->
            <div class="lv-action">
                <?php if ($log['Statut_Livraison'] === 'livree'): ?>
                    <div class="lv-done-state">
                        <i class="fas fa-check-circle"></i>
                        <p>Livraison terminée</p>
                        <small>Remise confirmée<?= $log['Id_Commande_B2B'] ? " par vous et réception confirmée par l'acheteur" : '' ?>.</small>
                    </div>

                <?php elseif ($log['Statut_Livraison'] === 'expediee' && !empty($log['Date_Confirmation_Livreur'])): ?>
                    <div class="lv-done-state">
                        <i class="fas fa-hourglass-half" style="color:var(--primary);"></i>
                        <p style="color:var(--primary);">Remise confirmée</p>
                        <small>En attente de la confirmation de réception par l'acheteur.</small>
                    </div>

                <?php elseif ($log['Statut_Livraison'] === 'expediee'): ?>
                    <div class="lv-action-hint">
                        <i class="fas fa-info-circle" style="margin-top:2px; color:var(--primary);"></i>
                        <?php if (!empty($log['Date_Confirmation_Acheteur'])): ?>
                            L'acheteur a déjà confirmé la réception : votre confirmation clôturera la livraison.
                        <?php else: ?>
                            Confirmez que vous avez remis le colis au destinataire.
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('Confirmer la remise du colis ?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="confirmer_remise">
                        <div class="mb-3">
                            <label class="form-label small text-body-secondary" for="note-remise">Note (optionnelle)</label>
                            <input type="text" name="notes" id="note-remise" class="form-control" maxlength="255" placeholder="Ex : remis à la réception, signature obtenue...">
                        </div>
                        <button type="submit" class="lv-btn btn btn-success">
                            <i class="fas fa-check-circle"></i> J'ai livré : confirmer la remise
                        </button>
                    </form>

                <?php elseif ($log['Statut_Livraison'] === 'annulee'): ?>
                    <div class="lv-action-hint mb-0">
                        <i class="fas fa-ban" style="margin-top:2px; color:var(--danger);"></i>
                        Cette livraison a été annulée.
                    </div>

                <?php else: ?>
                    <div class="lv-action-hint mb-0">
                        <i class="fas fa-info-circle" style="margin-top:2px; color:var(--primary);"></i>
                        Expédition pas encore validée par le propriétaire.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($lat_dst && $lng_dst): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('livreur-map').setView([<?= $lat_dst ?>, <?= $lng_dst ?>], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    L.marker([<?= $lat_dst ?>, <?= $lng_dst ?>])
     .addTo(map)
     .bindPopup('<b><?= htmlspecialchars($dest) ?></b>').openPopup();
});
</script>
<?php endif; ?>

</body></html>
