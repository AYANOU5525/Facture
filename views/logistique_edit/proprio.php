<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>

<div class="container fade-in py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-body-secondary mb-0">Mise à jour du suivi d'expédition</p>
        <a href="logistique.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

    <div class="card">
        <div class="card-body">
        <h2 class="fs-5 mb-4">
            <?php if ($log['Id_Commande_B2B']): ?>
                Commande B2B <?= htmlspecialchars($log['Numero_Commande'] ?? '') ?> — Acheteur : <?= htmlspecialchars($log['Nom_Acheteur'] ?? '') ?>
            <?php else: ?>
                Vente <?= htmlspecialchars($log['Numero_Vente'] ?? '') ?> — Client : <?= htmlspecialchars($log['Nom_Client'] ?? '') ?>
            <?php endif; ?>
        </h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Transporteur</label>
                    <input type="text" name="transporteur" class="form-control" value="<?= htmlspecialchars($log['Transporteur'] ?? '') ?>" placeholder="DHL, FedEx... (vide = Livraison directe à l'expédition)">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Numéro de Suivi</label>
                    <input type="text" name="numero_suivi" class="form-control" value="<?= htmlspecialchars($log['Numero_Suivi'] ?? '') ?>" placeholder="Vide = généré automatiquement à l'expédition">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Statut de Livraison</label>
                    <select name="statut" class="form-select" required>
                        <option value="traitement" <?= $log['Statut_Livraison'] == 'traitement' ? 'selected' : '' ?>>En préparation (Traitement)</option>
                        <option value="en_attente" <?= $log['Statut_Livraison'] == 'en_attente' ? 'selected' : '' ?>>En attente d'enlèvement</option>
                        <option value="expediee" <?= $log['Statut_Livraison'] == 'expediee' ? 'selected' : '' ?>>Expédiée (En livraison)</option>
                        <option value="livree" <?= $log['Statut_Livraison'] == 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $log['Statut_Livraison'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date d'Expédition</label>
                    <input type="datetime-local" name="date_expedition" class="form-control" value="<?= $log['Date_Expedition'] ? date('Y-m-d\TH:i', strtotime($log['Date_Expedition'])) : '' ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date de Livraison Prévue</label>
                    <input type="date" name="date_prevue" class="form-control" value="<?= $log['Date_Livraison_Prevue'] ? date('Y-m-d', strtotime($log['Date_Livraison_Prevue'])) : '' ?>" placeholder="Vide = expédition + 3 jours">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date de Livraison Réelle</label>
                    <input type="datetime-local" name="date_livraison" class="form-control" value="<?= $log['Date_Livraison_Effectuee'] ? date('Y-m-d\TH:i', strtotime($log['Date_Livraison_Effectuee'])) : '' ?>">
                </div>
            </div>

            <!-- Adresse de livraison & Carte de navigation -->
            <div class="mt-4 pt-3 border-top">
                <h4 class="fs-6 mb-3"><i class="fas fa-map-marked-alt text-primary"></i> Carte d'Itinéraire &amp; Suivi Logistique</h4>

                <div class="mb-3">
                    <label class="form-label">Adresse de Livraison</label>
                    <textarea name="adresse_livraison" id="adresse_livraison" class="form-control" rows="2" <?= $log['Id_Commande_B2B'] ? 'readonly' : '' ?>><?= htmlspecialchars($log['Adresse_Livraison'] ?? $log['Adresse_Acheteur'] ?? '') ?></textarea>
                </div>

                <?php if (!$log['Id_Commande_B2B']): ?>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="address-search" class="form-control" placeholder="Rechercher l'adresse du client...">
                        <button type="button" class="btn btn-primary" onclick="rechercherDest()"><i class="fas fa-search"></i> Chercher</button>
                    </div>
                <?php endif; ?>

                <div id="logistics-map" class="rounded mb-3" style="height: 380px; width: 100%; border: 1px solid var(--bs-border-color); z-index: 1;"></div>

                <input type="hidden" name="lat_livraison" id="lat_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lat'] ?? $log['Lat_Acheteur'] ?? '') ?>">
                <input type="hidden" name="lng_livraison" id="lng_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lng'] ?? $log['Lng_Acheteur'] ?? '') ?>">
            </div>

            <div class="mt-3">
                <label class="form-label">Notes & Observations</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($log['Notes_Logistique'] ?? '') ?></textarea>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="route-info" class="text-body-secondary small">Calcul d'itinéraire...</div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer les modifications</button>
            </div>
        </form>
        </div>
    </div>
</div>

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
