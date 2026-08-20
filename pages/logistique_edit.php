<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

requireRole(ROLE_PROPRIO, ROLE_LIVREUR);

use App\Application\Logistics\LogisticsService;
use App\Infrastructure\Persistence\LogisticsRepository;

$logisticsService = new LogisticsService($pdo, new LogisticsRepository($pdo));

if (!isset($_GET['id'])) {
    header('Location: logistique.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}

$id_logistique = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// Récupérer l'entrée logistique
$log = $logisticsService->find($id_logistique, (int) $entreprise_id);

if (!$log) {
    die("Entrée logistique non trouvée ou accès refusé.");
}

$error = '';
$success = '';

$transporteur = trim($_POST['transporteur'] ?? '');
$numero_suivi = trim($_POST['numero_suivi'] ?? '');
$statut = $_POST['statut'] ?? 'traitement';
$statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
$date_exp = $_POST['date_expedition'] ?? null;
$date_prevue = $_POST['date_prevue'] ?? null;
$date_livree = $_POST['date_livraison'] ?? null;
$notes = $_POST['notes'] ?? '';
$adresse_livraison = $_POST['adresse_livraison'] ?? '';
$lat_livraison = !empty($_POST['lat_livraison']) ? floatval($_POST['lat_livraison']) : null;
$lng_livraison = !empty($_POST['lng_livraison']) ? floatval($_POST['lng_livraison']) : null;

try {
    if (!in_array($statut, $statuts_valides, true)) {
        throw new InvalidArgumentException('Statut de livraison invalide.');
    }

    if ($statut === 'expediee') {
        if ($transporteur === '' || $numero_suivi === '') {
            throw new InvalidArgumentException('Le transporteur et le numéro de suivi sont requis pour une expédition.');
        }
        $date_exp = $date_exp ?: date('Y-m-d H:i:s');
    }

    if ($statut === 'livree') {
        $date_livree = $date_livree ?: date('Y-m-d H:i:s');
    }

    $event = $logisticsService->update($id_logistique, (int) $entreprise_id, [
        'carrier' => $transporteur,
        'tracking' => $numero_suivi,
        'status' => $statut,
        'date_expedition' => $date_exp,
        'date_prevue' => $date_prevue,
        'date_livraison' => $date_livree,
        'notes' => $notes,
        'address' => $adresse_livraison,
        'latitude' => $lat_livraison,
        'longitude' => $lng_livraison,
        'command_id' => (int) ($log['Id_Commande_B2B'] ?? 0),
    ]);

    if ($event) {
        require_once '../includes/b2b_helpers.php';
        $cmd = $event['command'];
        $id_cmd = (int) $log['Id_Commande_B2B'];
        $note = $event['new_status'] === 'expediee'
            ? "Mis en livraison (N° Suivi: $numero_suivi)"
            : 'Livrée par le transporteur';
        enregistrerHistoriqueCommande($pdo, $id_cmd, $event['old_status'], $event['new_status'], $note, $entreprise_id);
        if ($event['new_status'] === 'expediee') {
            creerNotificationB2b($pdo, (int) $cmd['Id_Entreprise_Acheteuse'], 'expedition', "🚚 Commande {$cmd['Numero_Commande']} en livraison", "Votre commande {$cmd['Numero_Commande']} a été expédiée via $transporteur (N° de suivi : $numero_suivi).", $id_cmd);
        } else {
            creerNotificationB2b($pdo, (int) $cmd['Id_Entreprise_Acheteuse'], 'livraison', "✅ Commande {$cmd['Numero_Commande']} livrée", "La livraison de votre commande {$cmd['Numero_Commande']} est arrivée.", $id_cmd);
            creerNotificationB2b($pdo, (int) $cmd['Id_Entreprise_Vendeuse'], 'reception', "🏆 Commande {$cmd['Numero_Commande']} livrée", "La livraison de votre commande {$cmd['Numero_Commande']} a été complétée.", $id_cmd);
        }
    }

    $success = "Le suivi logistique a été mis à jour.";
    // Rafraîchir les données
    $log = $logisticsService->find($id_logistique, (int) $entreprise_id);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Erreur lors de la mise à jour : " . $e->getMessage();
}

$page_title = 'Modifier Expédition';
include '../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Mise à jour Expédition</h1>
        <a href="logistique.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 20px;">
            <?php if ($log['Id_Commande_B2B']): ?>
                Commande B2B <?= htmlspecialchars($log['Numero_Commande'] ?? '') ?> — Acheteur : <?= htmlspecialchars($log['Nom_Acheteur'] ?? '') ?>
            <?php else: ?>
                Vente <?= htmlspecialchars($log['Numero_Vente'] ?? '') ?> — Client : <?= htmlspecialchars($log['Nom_Client'] ?? '') ?>
            <?php endif; ?>
        </h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Transporteur</label>
                    <input type="text" name="transporteur" class="form-control" value="<?= htmlspecialchars($log['Transporteur'] ?? '') ?>" placeholder="ex: DHL, Fedex, GP...">
                </div>
                <div class="form-group">
                    <label>Numéro de Suivi</label>
                    <input type="text" name="numero_suivi" class="form-control" value="<?= htmlspecialchars($log['Numero_Suivi'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Statut de Livraison</label>
                    <select name="statut" class="form-control" required>
                        <option value="traitement" <?= $log['Statut_Livraison'] == 'traitement' ? 'selected' : '' ?>>En préparation (Traitement)</option>
                        <option value="en_attente" <?= $log['Statut_Livraison'] == 'en_attente' ? 'selected' : '' ?>>En attente d'enlèvement</option>
                        <option value="expediee" <?= $log['Statut_Livraison'] == 'expediee' ? 'selected' : '' ?>>Expédiée (En livraison)</option>
                        <option value="livree" <?= $log['Statut_Livraison'] == 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $log['Statut_Livraison'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date d'Expédition</label>
                    <input type="datetime-local" name="date_expedition" class="form-control" value="<?= $log['Date_Expedition'] ? date('Y-m-d\TH:i', strtotime($log['Date_Expedition'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Date de Livraison Prévue</label>
                    <input type="date" name="date_prevue" class="form-control" value="<?= $log['Date_Livraison_Prevue'] ? date('Y-m-d', strtotime($log['Date_Livraison_Prevue'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Date de Livraison Réelle</label>
                    <input type="datetime-local" name="date_livraison" class="form-control" value="<?= $log['Date_Livraison_Effectuee'] ? date('Y-m-d\TH:i', strtotime($log['Date_Livraison_Effectuee'])) : '' ?>">
                </div>
            </div>

            <!-- Adresse de livraison & Carte de navigation -->
            <div style="margin-top: 25px; border-top: 1px solid var(--zinc-200); padding-top: 20px;">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-map-marked-alt"></i> Carte d'Itinéraire & Suivi Logistique</h4>

                <div class="form-group">
                    <label>Adresse de Livraison</label>
                    <textarea name="adresse_livraison" id="adresse_livraison" class="form-control" rows="2" <?= $log['Id_Commande_B2B'] ? 'readonly' : '' ?>><?= htmlspecialchars($log['Adresse_Livraison'] ?? $log['Adresse_Acheteur'] ?? '') ?></textarea>
                </div>

                <?php if (!$log['Id_Commande_B2B']): ?>
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="text" id="address-search" class="form-control form-control-sm" placeholder="Rechercher l'adresse du client...">
                        <button type="button" class="btn btn-primary btn-sm" onclick="rechercherDest()"><i class="fas fa-search"></i> Chercher</button>
                    </div>
                <?php endif; ?>

                <div id="logistics-map" style="height: 380px; width: 100%; border-radius: 12px; border: 1px solid var(--zinc-300); z-index: 1; margin-bottom: 20px;"></div>

                <input type="hidden" name="lat_livraison" id="lat_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lat'] ?? $log['Lat_Acheteur'] ?? '') ?>">
                <input type="hidden" name="lng_livraison" id="lng_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lng'] ?? $log['Lng_Acheteur'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Notes & Observations</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($log['Notes_Logistique'] ?? '') ?></textarea>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div id="route-info" class="text-muted" style="font-size:0.9rem;">Calcul d'itinéraire...</div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<?php
// Déterminer coordonnées de départ (vendeur) et destination (acheteur)
$lat_dep = !empty($log['Lat_Vendeur']) ? floatval($log['Lat_Vendeur']) : (!empty($log['Ma_Latitude']) ? floatval($log['Ma_Latitude']) : 6.1372);
$lng_dep = !empty($log['Lng_Vendeur']) ? floatval($log['Lng_Vendeur']) : (!empty($log['Ma_Longitude']) ? floatval($log['Ma_Longitude']) : 1.2125);
$label_dep = $log['Nom_Vendeur'] ?? $log['Mon_Nom_Entreprise'] ?? 'Départ';

$lat_dst = !empty($log['Adresse_Livraison_Lat']) ? floatval($log['Adresse_Livraison_Lat']) : (!empty($log['Lat_Acheteur']) ? floatval($log['Lat_Acheteur']) : null);
$lng_dst = !empty($log['Adresse_Livraison_Lng']) ? floatval($log['Adresse_Livraison_Lng']) : (!empty($log['Lng_Acheteur']) ? floatval($log['Lng_Acheteur']) : null);
$label_dst = $log['Nom_Acheteur'] ?? $log['Nom_Client'] ?? 'Destination';
$is_b2b = $log['Id_Commande_B2B'] ? 1 : 0;
?>

<script>
    let map, markerDep, markerDst, routeLine;
    const latDep = <?= $lat_dep ?>;
    const lngDep = <?= $lng_dep ?>;
    const labelDep = "<?= htmlspecialchars($label_dep) ?>";

    let latDst = <?= $lat_dst !== null ? $lat_dst : 'null' ?>;
    let lngDst = <?= $lng_dst !== null ? $lng_dst : 'null' ?>;
    const labelDst = "<?= htmlspecialchars($label_dst) ?>";
    const isB2B = <?= $is_b2b ?>;

    function initMap() {
        // Centrer sur départ ou Lomé
        const centerLat = latDst || latDep;
        const centerLng = lngDst || lngDep;

        map = L.map('logistics-map').setView([centerLat, centerLng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Marqueur départ (Rouge)
        const redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        markerDep = L.marker([latDep, lngDep], {
                icon: redIcon
            }).addTo(map)
            .bindPopup(`<b>Départ : ${labelDep}</b>`).openPopup();

        // Si destination présente, l'ajouter
        if (latDst && lngDst) {
            ajouterMarqueurDest(latDst, lngDst);
            tracerItineraire();
        } else {
            document.getElementById('route-info').textContent = "Veuillez sélectionner la destination sur la carte.";
        }

        // Si vente directe, clic sur la carte pose la destination
        if (!isB2B) {
            map.on('click', function(e) {
                latDst = e.latlng.lat;
                lngDst = e.latlng.lng;
                document.getElementById('lat_livraison').value = latDst;
                document.getElementById('lng_livraison').value = lngDst;

                ajouterMarqueurDest(latDst, lngDst);
                tracerItineraire();
                reverseGeocodeDest(latDst, lngDst);
            });
        }
    }

    function ajouterMarqueurDest(lat, lng) {
        if (markerDst) map.removeLayer(markerDst);

        // Icône bleue standard ou verte
        const greenIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        markerDst = L.marker([lat, lng], {
                icon: greenIcon,
                draggable: !isB2B
            }).addTo(map)
            .bindPopup(`<b>Destination : ${labelDst}</b>`);

        if (!isB2B) {
            markerDst.on('dragend', function() {
                const pos = markerDst.getLatLng();
                latDst = pos.lat;
                lngDst = pos.lng;
                document.getElementById('lat_livraison').value = latDst;
                document.getElementById('lng_livraison').value = lngDst;
                tracerItineraire();
                reverseGeocodeDest(latDst, lngDst);
            });
        }
    }

    async function tracerItineraire() {
        if (!latDep || !lngDep || !latDst || !lngDst) return;

        if (routeLine) map.removeLayer(routeLine);

        try {
            const url = `https://router.project-osrm.org/route/v1/driving/${lngDep},${latDep};${lngDst},${latDst}?overview=full&geometries=geojson`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                const routeGeom = route.geometry;
                const distKm = (route.distance / 1000).toFixed(1);
                const durationMin = Math.round(route.duration / 60);

                routeLine = L.geoJSON(routeGeom, {
                    style: {
                        color: '#4f46e5',
                        weight: 5,
                        opacity: 0.8
                    }
                }).addTo(map);

                // Ajuster la vue pour voir les deux points
                const group = new L.featureGroup([markerDep, markerDst]);
                map.fitBounds(group.getBounds().pad(0.1));

                document.getElementById('route-info').innerHTML =
                    `<i class="fas fa-route"></i> Distance : <strong>${distKm} km</strong> | Durée approx. : <strong>${durationMin} min</strong>`;
            } else {
                // Ligne droite en fallback
                routeLine = L.polyline([
                    [latDep, lngDep],
                    [latDst, lngDst]
                ], {
                    color: '#ef4444',
                    dashArray: '5, 10'
                }).addTo(map);
                document.getElementById('route-info').textContent = "Trajet routier indisponible. Affichage d'une ligne directe.";
            }
        } catch (e) {
            console.error("OSRM Routing error: ", e);
            routeLine = L.polyline([
                [latDep, lngDep],
                [latDst, lngDst]
            ], {
                color: '#ef4444',
                dashArray: '5, 10'
            }).addTo(map);
            document.getElementById('route-info').textContent = "Calcul d'itinéraire impossible (erreur serveur).";
        }
    }

    async function reverseGeocodeDest(lat, lng) {
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
            const data = await response.json();
            if (data && data.display_name) {
                document.getElementById('adresse_livraison').value = data.display_name;
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function rechercherDest() {
        const query = document.getElementById('address-search').value.trim();
        if (!query) return;

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
            const results = await response.json();
            if (results && results.length > 0) {
                const res = results[0];
                latDst = parseFloat(res.lat);
                lngDst = parseFloat(res.lon);

                document.getElementById('lat_livraison').value = latDst;
                document.getElementById('lng_livraison').value = lngDst;
                document.getElementById('adresse_livraison').value = res.display_name;

                map.setView([latDst, lngDst], 14);
                ajouterMarqueurDest(latDst, lngDst);
                tracerItineraire();
            } else {
                alert("Adresse non trouvée.");
            }
        } catch (e) {
            console.error(e);
        }
    }

    document.addEventListener('DOMContentLoaded', initMap);
</script>

</body>

</html>