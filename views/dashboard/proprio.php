<div class="container fade-in py-4">

    <!-- EN-TÊTE -->
    <div class="mb-3">
        <h1 class="fs-4 fw-bold mb-1"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-body-secondary small mb-0">Aperçu de l'activité de votre entreprise — <?= date('d/m/Y') ?></p>
    </div>

    <!-- ZONE SUPÉRIEURE : CA / VENTES / COMMANDES EN COURS -->
    <div class="dash-kpi-row">
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">Chiffre d'affaires</div>
            <div class="dash-kpi-value"><?= number_format($total_ca, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Ventes</div>
            <div class="dash-kpi-value"><?= $nb_ventes ?></div>
        </div>
        <div class="dash-kpi <?= count($b2b) > 0 ? 'is-danger' : '' ?>">
            <div class="dash-kpi-label">Commandes B2B en attente</div>
            <div class="dash-kpi-value"><?= count($b2b) ?></div>
        </div>
    </div>

    <!-- GRAPHIQUE D'ACTIVITÉ -->
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h6 mb-0"><i class="fas fa-chart-column text-primary me-2"></i> Activité — chiffre d'affaires sur 6 mois</h2>
        </div>
        <div class="card-body">
            <canvas id="caChart" height="90"></canvas>
        </div>
    </div>

    <!-- ALERTES STOCK -->
    <?php if (!empty($produits_alerte)): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Alertes stock</h2>
                <a href="products.php" class="link-primary text-decoration-none fw-medium small">Voir les produits</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($produits_alerte as $pa): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($pa['Nom_Produit']) ?></span>
                        <span class="badge text-bg-warning"><?= (int) $pa['Quantite_En_Stock'] ?> restant(s)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- COMMANDES B2B EN ATTENTE -->
    <?php if (estProprietaire()): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="fas fa-comments text-primary me-2"></i> Commandes B2B à valider</h2>
                <a href="commandes_b2b.php?onglet=recues" class="link-primary text-decoration-none fw-medium small">Gérer</a>
            </div>
            <?php if ($b2b): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Entreprise</th>
                                <th>Commande</th>
                                <th class="text-end">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($b2b as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['Nom_Entreprise']) ?></td>
                                    <td class="text-body-secondary small"><?= htmlspecialchars($c['Numero_Commande']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($c['Montant_Total'], 0, ',', ' ') ?> F</td>
                                    <td class="text-end"><a href="commandes_b2b.php?onglet=recues" class="btn btn-sm btn-outline-secondary">Voir</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="card-body text-center text-body-secondary py-4">
                    <p class="mb-0 small">Aucune commande en attente de validation.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ACTIVITÉ RÉCENTE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Activité récente</h2>
            <a href="sales.php" class="link-primary text-decoration-none fw-medium small">Tout voir</a>
        </div>
        <?php if ($ventes_recentes): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Référence</th>
                            <th>Date</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventes_recentes as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['Nom_Client']) ?></td>
                                <td class="text-body-secondary small"><?= htmlspecialchars($v['Numero_Vente']) ?></td>
                                <td class="text-body-secondary small"><?= date('d/m/Y H:i', strtotime($v['Date_Vente'])) ?></td>
                                <td class="text-end fw-bold"><?= number_format($v['Montant_Total'], 0, ',', ' ') ?> F</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card-body text-center text-body-secondary py-4">
                <p class="mb-0 small">Aucune vente enregistrée.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    (function() {
        const labels = <?= json_encode($mois_labels) ?>;
        const data = <?= json_encode($ca_data) ?>;

        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const gridColor = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const textColor = dark ? '#8a8f9a' : '#6b7076';

        const ctx = document.getElementById('caChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Chiffre d\'Affaires (F)',
                    data,
                    backgroundColor: 'rgba(0, 70, 255, 0.15)',
                    borderColor: '#0046ff',
                    borderWidth: 2,
                    borderRadius: 4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => new Intl.NumberFormat('fr-FR').format(ctx.parsed.y) + ' F'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: textColor
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor,
                            callback: v => new Intl.NumberFormat('fr-FR', {
                                notation: 'compact'
                            }).format(v)
                        }
                    }
                }
            }
        });
    })();
</script>

</body>

</html>
