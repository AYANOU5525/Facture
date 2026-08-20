<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/b2b_helpers.php';

requireRole(ROLE_PROPRIO);

if (!isset($_SESSION['entreprise_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$entreprise_id = (int) $_SESSION['entreprise_id'];

$stmt = $pdo->prepare("
    SELECT
        Id_Entreprise,
        Nom_Entreprise,
        Latitude,
        Longitude,
        Ville,
        Region
    FROM Entreprise
    WHERE Id_Entreprise = ?
");

$stmt->execute([$entreprise_id]);

$mon_entreprise = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mon_entreprise) {
    die("Entreprise introuvable.");
}

$mon_latitude = $mon_entreprise['Latitude'];
$mon_longitude = $mon_entreprise['Longitude'];

$j_ai_coords =
    $mon_latitude !== null &&
    $mon_longitude !== null &&
    $mon_latitude !== '' &&
    $mon_longitude !== '';


$j_lat = $j_ai_coords
    ? (float) $mon_latitude
    : null;

$j_lon = $j_ai_coords
    ? (float) $mon_longitude
    : null;


$secteur_filtre = trim($_GET['secteur'] ?? '');
$ville_filtre   = trim($_GET['ville'] ?? '');
$region_filtre  = trim($_GET['region'] ?? '');

$distance_max = isset($_GET['distance'])
    ? max(0, (int) $_GET['distance'])
    : 0;

$tri_distance =
    isset($_GET['autour_de_moi']) &&
    $_GET['autour_de_moi'] === '1' &&
    $j_ai_coords;


// ============================================================
// RÉCUPÉRATION DES ENTREPRISES
// ============================================================

$sql = "
    SELECT
        Id_Entreprise,
        Nom_Entreprise,
        Secteur_Activite,
        Description_Entreprise,
        Adresse_Entreprise,
        Tel_Entreprise,
        Email_Entreprise,
        Score_Fiabilite,
        Nombre_Commandes_Completees,
        Latitude,
        Longitude,
        Ville,
        Region
    FROM Entreprise
    WHERE Id_Entreprise != ?
";

$params = [$entreprise_id];

if ($secteur_filtre !== '') {
    $sql .= " AND Secteur_Activite = ?";
    $params[] = $secteur_filtre;
}

if ($ville_filtre !== '') {
    $sql .= " AND Ville = ?";
    $params[] = $ville_filtre;
}

if ($region_filtre !== '') {
    $sql .= " AND Region = ?";
    $params[] = $region_filtre;
}

$sql .= "
    ORDER BY
        Score_Fiabilite DESC,
        Nombre_Commandes_Completees DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$entreprises = $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($entreprises as &$entreprise) {

    $entreprise['distance_km'] = null;
    $entreprise['distance_label'] = 'Distance inconnue';
    $a_des_coords =
        isset($entreprise['Latitude']) &&
        isset($entreprise['Longitude']) &&
        $entreprise['Latitude'] !== null &&
        $entreprise['Longitude'] !== null &&
        $entreprise['Latitude'] !== '' &&
        $entreprise['Longitude'] !== '';
    if ($j_ai_coords && $a_des_coords) {

        $distance = calculDistanceHaversine(
            $j_lat,
            $j_lon,
            (float) $entreprise['Latitude'],
            (float) $entreprise['Longitude']
        );

        $entreprise['distance_km'] = $distance;

        $entreprise['distance_label'] =
            formaterDistance($distance);
    }
    $entreprise['reactivite'] =
        getTempsReponseMoyen(
            $pdo,
            (int) $entreprise['Id_Entreprise']
        );
}

unset($entreprise);
if ($distance_max > 0 && $j_ai_coords) {

    $entreprises = array_filter(
        $entreprises,
        function ($entreprise) use ($distance_max) {

            return
                $entreprise['distance_km'] !== null &&
                $entreprise['distance_km'] <= $distance_max;
        }
    );

    $entreprises = array_values($entreprises);
}

if ($tri_distance) {

    usort(
        $entreprises,
        function ($a, $b) {

            $distance_a =
                $a['distance_km'] ?? PHP_INT_MAX;

            $distance_b =
                $b['distance_km'] ?? PHP_INT_MAX;

            return $distance_a <=> $distance_b;
        }
    );
}



$stmt = $pdo->query("
    SELECT DISTINCT Secteur_Activite
    FROM Entreprise
    WHERE Secteur_Activite IS NOT NULL
      AND Secteur_Activite <> ''
    ORDER BY Secteur_Activite
");

$secteurs = $stmt->fetchAll(PDO::FETCH_COLUMN);


$stmt = $pdo->query("
    SELECT DISTINCT Ville
    FROM Entreprise
    WHERE Ville IS NOT NULL
      AND Ville <> ''
    ORDER BY Ville
");

$villes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("
    SELECT DISTINCT Region
    FROM Entreprise
    WHERE Region IS NOT NULL
      AND Region <> ''
    ORDER BY Region
");

$regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Réseau B2B";
require_once __DIR__ . '/../includes/header.php';

?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-network-wired"></i> Réseau B2B</h1>
        <p>Découvrez et contactez vos partenaires commerciaux</p>
    </div>


    <div class="card filters-panel">
        <form method="GET" id="filtres-form">
            <div class="filters-grid">
                <!-- Secteur -->
                <div class="filter-group">
                    <label for="filtre-secteur"><i class="fas fa-industry"></i> Secteur</label>
                    <select id="filtre-secteur" name="secteur" class="form-control" onchange="this.form.submit()">
                        <option value="">Tous les secteurs</option>
                        <?php foreach ($secteurs as $sec): ?>
                            <option value="<?= htmlspecialchars($sec) ?>"
                                <?= $secteur_filtre === $sec ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sec) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ville -->
                <div class="filter-group">
                    <label for="filtre-ville"><i class="fas fa-city"></i> Ville</label>
                    <select id="filtre-ville" name="ville" class="form-control" onchange="this.form.submit()">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villes as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"
                                <?= $ville_filtre === $v ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Région -->
                <div class="filter-group">
                    <label for="filtre-region"><i class="fas fa-globe-africa"></i> Pays / Région</label>
                    <select id="filtre-region" name="region" class="form-control" onchange="this.form.submit()">
                        <option value="">Tous les pays</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                <?= $region_filtre === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Distance (si coordonnées dispo) -->
                <?php if ($j_ai_coords): ?>
                    <div class="filter-group">
                        <label for="filtre-distance"><i class="fas fa-route"></i> Distance max</label>
                        <select id="filtre-distance" name="distance" class="form-control" onchange="this.form.submit()">
                            <option value="0">Toutes distances</option>
                            <option value="500" <?= $distance_max == 500  ? 'selected' : '' ?>>500 km</option>
                            <option value="1000" <?= $distance_max == 1000 ? 'selected' : '' ?>>1 000 km</option>
                            <option value="2000" <?= $distance_max == 2000 ? 'selected' : '' ?>>2 000 km</option>
                            <option value="5000" <?= $distance_max == 5000 ? 'selected' : '' ?>>5 000 km</option>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Boutons -->
                <div class="filter-group filter-actions">
                    <?php if ($j_ai_coords): ?>
                        <a href="?autour_de_moi=1<?= $secteur_filtre ? '&secteur=' . urlencode($secteur_filtre) : '' ?>"
                            class="btn btn-primary btn-sm <?= $tri_distance ? 'active' : '' ?>">
                            <i class="fas fa-location-arrow"></i> Autour de moi
                        </a>
                    <?php endif; ?>
                    <a href="reseau_b2b.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </div>
        </form>

        <!-- Résumé des filtres actifs -->
        <?php if ($secteur_filtre || $ville_filtre || $region_filtre || $distance_max || $tri_distance): ?>
            <div class="filters-active">
                <i class="fas fa-filter"></i>
                <span><?= count($entreprises) ?> résultat(s)</span>
                <?php if ($secteur_filtre): ?>
                    <span class="filter-chip"><?= htmlspecialchars($secteur_filtre) ?></span>
                <?php endif; ?>
                <?php if ($ville_filtre): ?>
                    <span class="filter-chip"><?= htmlspecialchars($ville_filtre) ?></span>
                <?php endif; ?>
                <?php if ($region_filtre): ?>
                    <span class="filter-chip"><?= htmlspecialchars($region_filtre) ?></span>
                <?php endif; ?>
                <?php if ($distance_max): ?>
                    <span class="filter-chip">≤ <?= number_format($distance_max, 0, ',', ' ') ?> km</span>
                <?php endif; ?>
                <?php if ($tri_distance): ?>
                    <span class="filter-chip">📍 Trié par distance</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Grille des entreprises -->
    <div class="entreprises-grid">
        <?php if (empty($entreprises)): ?>
            <div class="card" style="grid-column:1/-1; text-align:center; padding:60px 20px; color:var(--text-muted);">
                <i class="fas fa-search" style="font-size:2.5rem; opacity:0.3; margin-bottom:15px;"></i>
                <p>Aucune entreprise trouvée avec ces critères.</p>
                <a href="reseau_b2b.php" class="btn btn-secondary btn-sm" style="margin-top:10px;">Voir toutes les entreprises</a>
            </div>
        <?php else: ?>
            <?php foreach ($entreprises as $e): ?>
                <?php
                $score = (int)$e['Score_Fiabilite'];
                if ($score >= 90) {
                    $score_classe = 'score-excellent';
                } elseif ($score >= 70) {
                    $score_classe = 'score-bon';
                } else {
                    $score_classe = 'score-moyen';
                }
                $react = $e['reactivite'];
                ?>
                <div class="entreprise-card">
                    <!-- En-tête -->
                    <div class="ent-header">
                        <div class="ent-avatar">
                            <?= strtoupper(substr($e['Nom_Entreprise'], 0, 1)) ?>
                        </div>
                        <div class="ent-title">
                            <h3><?= htmlspecialchars($e['Nom_Entreprise']) ?></h3>
                            <p class="ent-secteur">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($e['Secteur_Activite'] ?? 'Secteur non renseigné') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Corps -->
                    <div class="ent-body">
                        <!-- Localisation -->
                        <?php if (!empty($e['Ville'])): ?>
                            <div class="ent-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($e['Ville']) ?>
                                <?= !empty($e['Region']) ? ', ' . htmlspecialchars($e['Region']) : '' ?>
                                <?php if ($e['distance_label'] && $j_ai_coords): ?>
                                    <span class="distance-chip">
                                        <i class="fas fa-route"></i>
                                        <?= htmlspecialchars($e['distance_label']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Description -->
                        <?php if (!empty($e['Description_Entreprise'])): ?>
                            <p class="ent-description">
                                "<?= htmlspecialchars($e['Description_Entreprise']) ?>"
                            </p>
                        <?php endif; ?>

                        <!-- Score fiabilité + Commandes -->
                        <div class="ent-stats">
                            <div class="stat-item">
                                <div class="stat-value <?= $score_classe ?>">
                                    <?= $score ?>
                                    <span class="stat-unit">/100</span>
                                </div>
                                <div class="stat-label">Fiabilité</div>
                                <div class="score-bar">
                                    <div class="score-fill <?= $score_classe ?>" style="width:<?= $score ?>%"></div>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($e['Nombre_Commandes_Completees'], 0, ',', ' ') ?></div>
                                <div class="stat-label">Commandes</div>
                            </div>
                        </div>

                        <!-- Indicateur de réactivité (Point 3) -->
                        <div class="reaction-badge <?= $react['classe'] ?>">
                            <i class="fas fa-bolt"></i>
                            <?= htmlspecialchars($react['label']) ?>
                        </div>
                    </div>

                    <!-- Pied de carte -->
                    <div class="ent-footer">
                        <a href="commandes_b2b.php?onglet=passees&vendeur=<?= $e['Id_Entreprise'] ?>"
                            class="btn btn-success btn-sm" style="flex:2;">
                            <i class="fas fa-shopping-cart"></i> Commander
                        </a>
                        <?php if (!empty($e['Tel_Entreprise'])): ?>
                            <a href="tel:<?= htmlspecialchars($e['Tel_Entreprise']) ?>"
                                class="btn btn-secondary btn-sm" title="<?= htmlspecialchars($e['Tel_Entreprise']) ?>">
                                <i class="fas fa-phone"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($e['Email_Entreprise'])): ?>
                            <a href="mailto:<?= htmlspecialchars($e['Email_Entreprise']) ?>"
                                class="btn btn-secondary btn-sm" title="<?= htmlspecialchars($e['Email_Entreprise']) ?>">
                                <i class="fas fa-envelope"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    /* ── Filtres ── */
    .filters-panel {
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid var(--zinc-200);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
        gap: 12px;
        align-items: flex-end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .filter-group .form-control {
        min-width: 0;
    }

    .filter-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-actions {
        flex-direction: row;
        gap: 6px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filters-active {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--zinc-100);
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .filter-chip {
        padding: 3px 10px;
        background: var(--primary-light);
        color: var(--primary);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    /* ── Grille entreprises ── */
    .entreprises-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    /* ── Carte entreprise ── */
    .entreprise-card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--zinc-200);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow 0.25s ease, transform 0.25s ease;
    }

    .entreprise-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-3px);
    }

    .ent-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px;
        background: linear-gradient(135deg, #f8fafc, #eef2ff);
        border-bottom: 1px solid var(--zinc-100);
    }

    .ent-avatar {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary), #7c3aed);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        font-weight: 800;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0, 70, 255, 0.25);
    }

    .ent-title h3 {
        font-size: 1rem;
        margin: 0 0 3px;
    }

    .ent-secteur {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ent-body {
        padding: 16px 18px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .ent-location {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--text-muted);
        flex-wrap: wrap;
    }

    .distance-chip {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 1px 7px;
        background: #e8f4fd;
        color: #1a73e8;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .ent-description {
        font-size: 0.82rem;
        font-style: italic;
        color: var(--text-muted);
        line-height: 1.4;
        margin: 0;
    }

    /* Stats */
    .ent-stats {
        display: flex;
        gap: 15px;
        padding: 10px;
        background: var(--zinc-50);
        border-radius: 8px;
    }

    .stat-item {
        flex: 1;
        text-align: center;
    }

    .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .stat-unit {
        font-size: 0.7rem;
        font-weight: 400;
    }

    .stat-label {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 1px;
    }

    .score-bar {
        height: 4px;
        background: var(--zinc-200);
        border-radius: 2px;
        margin-top: 4px;
        overflow: hidden;
    }

    .score-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.5s ease;
    }

    .score-excellent .stat-value,
    .score-fill.score-excellent {
        color: var(--success);
        background: var(--success);
    }

    .score-bon .stat-value,
    .score-fill.score-bon {
        color: #2980b9;
        background: #2980b9;
    }

    .score-moyen .stat-value,
    .score-fill.score-moyen {
        color: var(--warning);
        background: var(--warning);
    }

    /* Réactivité */
    .reaction-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        width: fit-content;
    }

    .reaction-excellent {
        background: #e6f4ea;
        color: #137333;
    }

    .reaction-bon {
        background: #e8f4fd;
        color: #1558a3;
    }

    .reaction-moyen {
        background: #fef7e0;
        color: #a37001;
    }

    .reaction-lent {
        background: #fce8e8;
        color: #9e1111;
    }

    .reaction-neutre {
        background: var(--zinc-100);
        color: var(--text-muted);
    }

    /* Footer */
    .ent-footer {
        display: flex;
        gap: 6px;
        padding: 12px 18px;
        border-top: 1px solid var(--zinc-100);
        background: white;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .filters-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .filter-actions {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 640px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            grid-column: auto;
            width: 100%;
        }

        .filter-actions .btn {
            flex: 1 1 140px;
        }

        .entreprises-grid {
            grid-template-columns: 1fr;
        }
    }
</style>