<style>
/* ── Client History Premium — ReactBits + Kokonut UI ── */

.ch-hero {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px 32px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.ch-hero::before {
    content: '';
}

.ch-hero::after {
    content: '';
}

.ch-avatar {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    font-weight: 800;
    font-family: 'Plus Jakarta Sans', sans-serif;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(79,110,247,0.15);
}

.ch-meta { flex: 1; min-width: 0; }

.ch-name {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 6px;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ch-sub {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ch-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.ch-type-b2b   { background: var(--primary-light); color: var(--primary); }
.ch-type-direct { background: var(--success-bg); color: var(--success); }

.ch-kpi {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    overflow: hidden;
    transition: all var(--duration-lg) var(--ease);
    box-shadow: var(--shadow-xs);
}

.ch-kpi:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: rgba(79,110,247,0.2);
}

.ch-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    margin-bottom: 4px;
}

.ch-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: var(--text-muted);
}

.ch-kpi-value {
    font-size: 1.5rem;
    font-weight: 800;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--text-main);
    line-height: 1;
}

.ch-kpi-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Timeline view */
.ch-timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
}

.ch-timeline::before {
    content: '';
    position: absolute;
    left: 23px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.ch-tl-item {
    display: grid;
    grid-template-columns: 48px 1fr;
    gap: 0 16px;
    padding: 0 0 16px 0;
    position: relative;
    animation: fadeInUp 0.35s var(--ease-out) both;
}

.ch-tl-dot {
    width: 48px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 14px;
    position: relative;
    z-index: 1;
}

.ch-tl-dot-inner {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2.5px solid var(--bg-card);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ch-tl-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all var(--duration) var(--ease);
    flex-wrap: wrap;
}

.ch-tl-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
    transform: translateX(3px);
}

.ch-tl-date {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 500;
    white-space: nowrap;
    min-width: 110px;
}

.ch-tl-ref {
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-light);
    padding: 3px 10px;
    border-radius: 6px;
    white-space: nowrap;
}

.ch-tl-type {
    font-size: 0.78rem;
    color: var(--text-muted);
    flex: 1;
}

.ch-tl-amount {
    font-size: 1rem;
    font-weight: 800;
    color: var(--success);
    font-family: 'Plus Jakarta Sans', sans-serif;
    white-space: nowrap;
    margin-left: auto;
}

.ch-tl-action {
    flex-shrink: 0;
}

/* Empty state */
.ch-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.ch-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 24px;
    background: var(--zinc-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--zinc-400);
    margin: 0 auto 20px;
    animation: float 3s ease-in-out infinite;
}

.ch-empty h3 { font-size: 1.1rem; margin-bottom: 8px; color: var(--text-main); }
.ch-empty p  { font-size: 0.9rem; }

/* View toggle */
.ch-view-toggle {
    display: flex;
    gap: 4px;
    background: var(--zinc-100);
    padding: 3px;
    border-radius: 8px;
}

.ch-view-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    background: transparent;
    transition: all var(--duration) var(--ease);
    display: flex;
    align-items: center;
    gap: 5px;
}

.ch-view-btn.active {
    background: var(--bg-card);
    color: var(--primary);
    box-shadow: var(--shadow-xs);
}

/* Table view */
.ch-table-view { display: none; }
.ch-timeline-view { display: block; }

@media (max-width: 768px) {
    .ch-hero { flex-direction: column; text-align: center; gap: 16px; padding: 22px; }
    .ch-hero::before, .ch-hero::after { display: none; }
    .ch-hero .btn { width: 100%; }
    .ch-tl-card { flex-direction: column; align-items: flex-start; gap: 10px; }
    .ch-tl-amount { margin-left: 0; }
    .ch-timeline::before { display: none; }
    .ch-tl-item { grid-template-columns: 1fr; }
    .ch-tl-dot { display: none; }
}

@media (max-width: 480px) {
    .ch-kpi-value { font-size: 1.2rem; }
    .ch-avatar { width: 56px; height: 56px; font-size: 1.2rem; }
}
</style>

<div class="container fade-in py-2">

    <!-- BACK BUTTON + PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-history text-primary"></i> Historique d'achat</h1>
            <p class="text-body-secondary mb-0">Transactions &amp; activité commerciale</p>
        </div>
        <a href="clients.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux clients
        </a>
    </div>

    <!-- HERO CARD — Client Profile -->
    <div class="ch-hero animate-fade-in-down">
        <div class="ch-avatar"><?= htmlspecialchars($initiales) ?></div>
        <div class="ch-meta">
            <div class="ch-name"><?= htmlspecialchars($client_name) ?></div>
            <div class="ch-sub">
                <?php if ($type === 'b2b'): ?>
                    <span class="ch-type-badge ch-type-b2b"><i class="fas fa-handshake"></i> Client B2B</span>
                <?php else: ?>
                    <span class="ch-type-badge ch-type-direct"><i class="fas fa-user"></i> Client Direct</span>
                <?php endif; ?>
                <?php if ($derniere_transaction): ?>
                    <span class="text-body-secondary small">
                        <i class="fas fa-clock me-1"></i>
                        Dernière transaction : <?= date('d/m/Y', strtotime($derniere_transaction)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($type === 'b2b' && $client_id): ?>
            <a href="reseau_b2b.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-globe"></i> Voir dans le réseau
            </a>
        <?php endif; ?>
    </div>

    <!-- KPI STRIP -->
    <div class="row g-3 mb-4 stagger-children">
        <!-- Total dépensé -->
        <div class="col-6 col-lg-3">
        <div class="ch-kpi h-100">
            <div class="ch-kpi-icon" style="background:var(--success-bg); color:var(--success);">
                <i class="fas fa-coins"></i>
            </div>
            <div class="ch-kpi-label">Volume total</div>
            <div class="ch-kpi-value"><?= number_format($total_montant, 0, ',', ' ') ?> F</div>
            <div class="ch-kpi-sub">Tous temps confondus</div>
        </div>
        </div>
        <!-- Nb transactions -->
        <div class="col-6 col-lg-3">
        <div class="ch-kpi h-100">
            <div class="ch-kpi-icon" style="background:var(--primary-light); color:var(--primary);">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="ch-kpi-label">Transactions</div>
            <div class="ch-kpi-value"><?= $nb_transactions ?></div>
            <div class="ch-kpi-sub">Commandes au total</div>
        </div>
        </div>
        <!-- Panier moyen -->
        <div class="col-6 col-lg-3">
        <div class="ch-kpi h-100">
            <div class="ch-kpi-icon" style="background:var(--warning-bg); color:var(--warning);">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="ch-kpi-label">Panier moyen</div>
            <div class="ch-kpi-value"><?= number_format($montant_moyen, 0, ',', ' ') ?> F</div>
            <div class="ch-kpi-sub">Par commande</div>
        </div>
        </div>
        <!-- Dernière commande -->
        <div class="col-6 col-lg-3">
        <div class="ch-kpi h-100">
            <div class="ch-kpi-icon" style="background:var(--info-bg); color:var(--info);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="ch-kpi-label">Dernière activité</div>
            <div class="ch-kpi-value" style="font-size:1rem;">
                <?= $derniere_transaction ? date('d/m/Y', strtotime($derniere_transaction)) : '—' ?>
            </div>
            <div class="ch-kpi-sub">
                <?php if ($derniere_transaction):
                    $diff = (new \DateTime())->diff(new \DateTime($derniere_transaction));
                    echo $diff->days === 0 ? "Aujourd'hui" : "Il y a " . $diff->days . " jour(s)";
                endif; ?>
            </div>
        </div>
        </div>
    </div>

    <!-- TRANSACTION HISTORY CARD -->
    <div class="card">
        <!-- Card Header -->
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="fs-6 mb-0">Historique des transactions</h2>
                <p class="text-body-secondary small mb-0">
                    <?= $nb_transactions ?> transaction<?= $nb_transactions > 1 ? 's' : '' ?> enregistrée<?= $nb_transactions > 1 ? 's' : '' ?>
                </p>
            </div>
            <!-- View toggle -->
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" id="btnTimeline" onclick="switchView('timeline')">
                    <i class="fas fa-stream"></i> Fil
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnTable" onclick="switchView('table')">
                    <i class="fas fa-table"></i> Tableau
                </button>
            </div>
        </div>

        <div class="card-body">
        <?php if (empty($history)): ?>
            <div class="ch-empty">
                <div class="ch-empty-icon"><i class="fas fa-inbox"></i></div>
                <h3>Aucune transaction</h3>
                <p>Ce client n'a encore aucune transaction enregistrée.</p>
            </div>
        <?php else: ?>

            <!-- TIMELINE VIEW -->
            <div class="ch-timeline-view" id="viewTimeline">
                <div class="ch-timeline">
                    <?php foreach ($history as $i => $item):
                        $etat = $item['Etat'] ?? '—';
                        $badge = 'secondary';
                        $dot_color = 'var(--zinc-300)';
                        if (in_array($etat, ['livree', 'Validée'])) { $badge = 'success'; $dot_color = 'var(--success)'; }
                        if ($etat === 'annulee') { $badge = 'danger'; $dot_color = 'var(--danger)'; }
                        if ($etat === 'expediee') { $badge = 'info'; $dot_color = 'var(--info)'; }
                        if (in_array($etat, ['en_attente', 'traitement'])) { $badge = 'warning'; $dot_color = 'var(--warning)'; }
                    ?>
                    <div class="ch-tl-item" style="animation-delay: <?= $i * 40 ?>ms;">
                        <div class="ch-tl-dot">
                            <div class="ch-tl-dot-inner" style="background:<?= $dot_color ?>; box-shadow: 0 0 0 3px color-mix(in srgb, <?= $dot_color ?> 20%, transparent);"></div>
                        </div>
                        <div class="ch-tl-card">
                            <div class="ch-tl-date">
                                <i class="fas fa-calendar-alt" style="margin-right:4px; opacity:0.5;"></i>
                                <?= date('d/m/Y H:i', strtotime($item['Date_Doc'])) ?>
                            </div>
                            <div class="ch-tl-ref"><?= htmlspecialchars($item['Reference'] ?? '') ?></div>
                            <div class="ch-tl-type"><?= $item['Type_Doc'] ?></div>
                            <span class="badge text-bg-<?= $badge ?>"><?= strtoupper($etat) ?></span>
                            <div class="ch-tl-amount">
                                <?= number_format($item['Montant_Total'], 0, ',', ' ') ?> F
                            </div>
                            <div class="ch-tl-action">
                                <a href="invoice_view.php?ref=<?= urlencode($item['Reference']) ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Voir la facture">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TABLE VIEW -->
            <div class="ch-table-view" id="viewTable">
                <div class="scrollable-list">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Référence</th>
                                <th>Type</th>
                                <th class="text-end">Montant</th>
                                <th class="text-center">Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $item):
                                $etat = $item['Etat'] ?? '—';
                                $badge = 'secondary';
                                if (in_array($etat, ['livree', 'Validée'])) $badge = 'success';
                                if ($etat === 'annulee') $badge = 'danger';
                                if ($etat === 'expediee') $badge = 'info';
                            ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($item['Date_Doc'])) ?></td>
                                <td>
                                    <span class="ch-tl-ref"><?= htmlspecialchars($item['Reference'] ?? '') ?></span>
                                </td>
                                <td><span class="text-body-secondary small"><?= $item['Type_Doc'] ?></span></td>
                                <td class="text-end fw-bold" style="color:var(--success);">
                                    <?= number_format($item['Montant_Total'], 0, ',', ' ') ?> F
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-<?= $badge ?>"><?= strtoupper($etat) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="invoice_view.php?ref=<?= urlencode($item['Reference']) ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Voir Facture">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
        </div>
    </div>

</div>

<script>
function switchView(view) {
    const timeline = document.getElementById('viewTimeline');
    const table    = document.getElementById('viewTable');
    const btnTl    = document.getElementById('btnTimeline');
    const btnTb    = document.getElementById('btnTable');

    if (view === 'timeline') {
        timeline.style.display = 'block';
        table.style.display    = 'none';
        btnTl.classList.add('active');
        btnTb.classList.remove('active');
    } else {
        timeline.style.display = 'none';
        table.style.display    = 'block';
        btnTb.classList.add('active');
        btnTl.classList.remove('active');
    }
}
</script>

</body>
</html>
