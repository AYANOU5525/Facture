<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>

<?php
$statut = $log['Statut_Livraison'];
$a_planifier = $statut === 'traitement';
// Destination modifiable seulement pour une vente directe pas encore expédiée (une commande
// B2B livre à l'adresse choisie par l'acheteur).
$adresse_modifiable = $a_planifier && !$is_b2b;
$livreur_assigne = !empty($log['Id_Livreur']);
?>

<style>
.lg-steps { display:flex; align-items:center; gap:0; margin-bottom:1.25rem; }
.lg-step { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; text-align:center; }
.lg-dot { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; border:2px solid var(--zinc-200); background:var(--zinc-100); color:var(--text-muted); }
.lg-dot.done { background:var(--success); border-color:var(--success); color:#fff; }
.lg-dot.active { background:var(--primary); border-color:var(--primary); color:#fff; box-shadow:0 0 0 4px rgba(0,70,255,.15); }
.lg-label { font-size:.75rem; font-weight:600; color:var(--text-muted); }
.lg-label.done { color:var(--success); } .lg-label.active { color:var(--primary); }
.lg-line { flex:1; height:2px; background:var(--zinc-200); margin:0 4px 20px; }
.lg-line.done { background:var(--success); } .lg-line.active { background:var(--primary); }
.lg-confirm { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid var(--zinc-200); border-radius:10px; }
.lg-confirm i { font-size:1.1rem; }
</style>

<div class="container fade-in py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-body-secondary mb-0">
            <?= $a_planifier ? "Renseignez le transporteur et la date prévue pour valider l'expédition" : 'Suivi de la livraison' ?>
        </p>
        <a href="logistique.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div> <?php endif; ?>

    <div class="card">
        <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <h2 class="fs-5 mb-0">
                <?php if ($log['Id_Commande_B2B']): ?>
                    Commande B2B <?= htmlspecialchars($log['Numero_Commande'] ?? '') ?> · Acheteur : <?= htmlspecialchars($log['Nom_Acheteur'] ?? '') ?>
                <?php else: ?>
                    Vente <?= htmlspecialchars($log['Numero_Vente'] ?? '') ?> · Client : <?= htmlspecialchars($log['Nom_Client'] ?? '') ?>
                <?php endif; ?>
            </h2>
            <span class="badge text-bg-<?= $sl['badge'] ?>"><i class="fas <?= $sl['icon'] ?>"></i> <?= htmlspecialchars($sl['label']) ?></span>
        </div>

        <?php if ($statut !== 'annulee'): ?>
        <div class="lg-steps">
            <?php foreach ($etapes as $n => $etape): ?>
                <?php if ($n > 0): ?><div class="lg-line <?= $etape['etat'] === 'pending' ? '' : ($etape['etat'] === 'done' ? 'done' : 'active') ?>"></div><?php endif; ?>
                <div class="lg-step">
                    <div class="lg-dot <?= $etape['etat'] ?>"><i class="fas <?= $etape['etat'] === 'done' ? 'fa-check' : $etape['icon'] ?>"></i></div>
                    <span class="lg-label <?= $etape['etat'] === 'pending' ? '' : $etape['etat'] ?>"><?= htmlspecialchars($etape['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-1">
            <div class="col-md-4">
                <div class="small text-body-secondary">N° de suivi</div>
                <code class="fs-6"><?= htmlspecialchars($log['Numero_Suivi'] ?: 'Attribué à la validation') ?></code>
            </div>
            <?php if (!$a_planifier): ?>
                <div class="col-md-4">
                    <div class="small text-body-secondary">Transporteur</div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($log['Transporteur'] ?? '-') ?>
                        <span class="small text-body-secondary fw-normal"><?= $livreur_assigne ? "(livreur de l'équipe)" : (!empty($log['Transporteur']) ? '(externe)' : '') ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small text-body-secondary">Expédiée le · prévue le</div>
                    <div class="fw-semibold">
                        <?= $log['Date_Expedition'] ? date('d/m/Y H:i', strtotime($log['Date_Expedition'])) : '-' ?>
                        · <?= $log['Date_Livraison_Prevue'] ? date('d/m/Y', strtotime($log['Date_Livraison_Prevue'])) : '-' ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($a_planifier): ?>
        <!-- ÉTAPE 1 : expédition à valider (tous les champs sont obligatoires) -->
        <form method="POST" id="form-expedition" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="expedier">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label">Transporteur <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 mb-2 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type_transporteur" id="tt-equipe" value="equipe" checked>
                            <label class="form-check-label" for="tt-equipe">Livreur de l'équipe</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type_transporteur" id="tt-externe" value="externe">
                            <label class="form-check-label" for="tt-externe">Transporteur externe</label>
                        </div>
                    </div>
                    <select name="livreur_id" id="livreur_id" class="form-select" required>
                        <option value="">Choisir un livreur…</option>
                        <?php foreach ($livreurs as $lv): ?>
                            <option value="<?= (int) $lv['Id_Utilisateur'] ?>">
                                <?= htmlspecialchars($lv['Nom_Utilisateur']) ?> (<?= $lv['Role_Utilisateur'] === 'livreur' ? 'Livreur' : 'Propriétaire' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="transporteur_externe" id="transporteur_externe" class="form-control d-none" maxlength="100"
                           placeholder="Nom du transporteur (DHL, taxi-moto, ...)">
                    <div class="form-text" id="aide-transporteur">Le livreur choisi verra cette livraison et confirmera la remise.</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="date_prevue">Date de livraison prévue <span class="text-danger">*</span></label>
                    <input type="date" name="date_prevue" id="date_prevue" class="form-control" required min="<?= date('Y-m-d') ?>"
                           value="<?= $log['Date_Livraison_Prevue'] ? date('Y-m-d', strtotime($log['Date_Livraison_Prevue'])) : '' ?>">
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label" for="notes">Notes (optionnel)</label>
                <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Consignes pour le livreur, référence du transporteur..."><?= htmlspecialchars($log['Notes_Logistique'] ?? '') ?></textarea>
            </div>

            <input type="hidden" name="adresse_livraison" id="adresse_livraison_input" value="<?= htmlspecialchars($log['Adresse_Livraison'] ?? $log['Adresse_Acheteur'] ?? '') ?>">
            <input type="hidden" name="lat_livraison" id="lat_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lat'] ?? $log['Lat_Acheteur'] ?? '') ?>">
            <input type="hidden" name="lng_livraison" id="lng_livraison" value="<?= htmlspecialchars($log['Adresse_Livraison_Lng'] ?? $log['Lng_Acheteur'] ?? '') ?>">
        </form>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <form method="POST" onsubmit="return confirm('Annuler cette livraison ?<?= $is_b2b ? ' La commande restera prête à expédier.' : '' ?>')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="annuler">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-ban"></i> Annuler la livraison</button>
            </form>
            <button type="submit" form="form-expedition" class="btn btn-primary"
                    onclick="return document.getElementById('form-expedition').checkValidity() ? confirm('Valider l\'expédition ?<?= $is_b2b ? ' La facture sera générée et l\\\'acheteur notifié.' : '' ?>') : true">
                <i class="fas fa-shipping-fast"></i> Valider l'expédition
            </button>
        </div>

        <?php elseif ($statut === 'expediee' || $statut === 'livree'): ?>
        <!-- ÉTAPES 2-3 : double confirmation -->
        <h3 class="fs-6 mt-4 mb-2">Confirmations</h3>
        <div class="row g-2">
            <div class="col-md-6">
                <div class="lg-confirm">
                    <i class="fas <?= !empty($log['Date_Confirmation_Livreur']) ? 'fa-check-circle text-success' : 'fa-circle text-body-secondary' ?>"></i>
                    <div>
                        <div class="fw-semibold">Remise par le livreur</div>
                        <div class="small text-body-secondary">
                            <?= !empty($log['Date_Confirmation_Livreur']) ? 'Confirmée le ' . date('d/m/Y H:i', strtotime($log['Date_Confirmation_Livreur'])) : 'En attente' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($is_b2b): ?>
            <div class="col-md-6">
                <div class="lg-confirm">
                    <i class="fas <?= !empty($log['Date_Confirmation_Acheteur']) ? 'fa-check-circle text-success' : 'fa-circle text-body-secondary' ?>"></i>
                    <div>
                        <div class="fw-semibold">Réception par l'acheteur</div>
                        <div class="small text-body-secondary">
                            <?= !empty($log['Date_Confirmation_Acheteur']) ? 'Confirmée le ' . date('d/m/Y H:i', strtotime($log['Date_Confirmation_Acheteur'])) : 'En attente (depuis ses Commandes B2B)' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($statut === 'expediee' && empty($log['Date_Confirmation_Livreur'])): ?>
            <form method="POST" class="mt-3" onsubmit="return confirm('Confirmer la remise du colis au destinataire ?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="confirmer_remise">
                <div class="input-group">
                    <input type="text" name="notes" class="form-control" maxlength="255" aria-label="Note de remise" placeholder="Note de remise (optionnelle)">
                    <button type="submit" class="btn btn-success"><i class="fas fa-hand-holding"></i> Confirmer la remise</button>
                </div>
                <div class="form-text">
                    <?= $livreur_assigne
                        ? 'Normalement confirmée par ' . htmlspecialchars($log['Nom_Livreur'] ?? 'le livreur') . ' ; vous pouvez la confirmer à sa place.'
                        : 'Transporteur externe : confirmez la remise une fois le colis livré.' ?>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($log['Notes_Logistique'])): ?>
            <div class="mt-3 small"><span class="text-body-secondary">Notes :</span> <?= nl2br(htmlspecialchars($log['Notes_Logistique'])) ?></div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Adresse de livraison & Carte de navigation -->
        <div class="mt-4 pt-3 border-top">
            <h4 class="fs-6 mb-3"><i class="fas fa-map-marked-alt text-primary"></i> Carte d'Itinéraire &amp; Suivi Logistique</h4>

            <div class="mb-3">
                <label class="form-label" for="adresse_livraison">Adresse de Livraison</label>
                <textarea id="adresse_livraison" class="form-control" rows="2" <?= $adresse_modifiable ? '' : 'readonly' ?>
                          oninput="document.getElementById('adresse_livraison_input') && (document.getElementById('adresse_livraison_input').value = this.value)"><?= htmlspecialchars($log['Adresse_Livraison'] ?? $log['Adresse_Acheteur'] ?? '') ?></textarea>
            </div>

            <?php if ($adresse_modifiable): ?>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="address-search" class="form-control" placeholder="Rechercher l'adresse du client...">
                    <button type="button" class="btn btn-primary" onclick="rechercherDest()"><i class="fas fa-search"></i> Chercher</button>
                </div>
            <?php endif; ?>

            <div id="logistics-map" class="rounded mb-2" style="height: 380px; width: 100%; border: 1px solid var(--bs-border-color); z-index: 1;"></div>
            <div id="route-info" class="text-body-secondary small">Calcul d'itinéraire...</div>
        </div>
        </div>
    </div>
</div>

<script>
    // Bascule livreur de l'équipe / transporteur externe : un seul des deux champs est requis.
    (function () {
        const equipe = document.getElementById('tt-equipe');
        if (!equipe) return;
        const externe = document.getElementById('tt-externe');
        const select = document.getElementById('livreur_id');
        const texte = document.getElementById('transporteur_externe');
        const aide = document.getElementById('aide-transporteur');
        function maj() {
            const interne = equipe.checked;
            select.classList.toggle('d-none', !interne);
            select.required = interne;
            texte.classList.toggle('d-none', interne);
            texte.required = !interne;
            aide.textContent = interne
                ? 'Le livreur choisi verra cette livraison et confirmera la remise.'
                : 'Vous confirmerez vous-même la remise une fois le colis livré.';
        }
        equipe.addEventListener('change', maj);
        externe.addEventListener('change', maj);
        maj();
    })();
</script>

<script>
    let map, markerDep, markerDst, routeLine;
    const latDep = <?= $lat_dep ?>;
    const lngDep = <?= $lng_dep ?>;
    const labelDep = <?= json_encode($label_dep) ?>;

    let latDst = <?= $lat_dst !== null ? $lat_dst : 'null' ?>;
    let lngDst = <?= $lng_dst !== null ? $lng_dst : 'null' ?>;
    const labelDst = <?= json_encode($label_dst) ?>;
    const isB2B = <?= $is_b2b ?>;
    const editable = <?= $adresse_modifiable ? 'true' : 'false' ?>;

    function majDestination(lat, lng) {
        latDst = lat;
        lngDst = lng;
        const latInput = document.getElementById('lat_livraison');
        const lngInput = document.getElementById('lng_livraison');
        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;
    }

    function majAdresse(texte) {
        document.getElementById('adresse_livraison').value = texte;
        const input = document.getElementById('adresse_livraison_input');
        if (input) input.value = texte;
    }

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
            shadowUrl: '../assets/vendor/leaflet/images/marker-shadow.png',
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

        // Vente directe à planifier : un clic sur la carte pose la destination
        if (editable) {
            map.on('click', function(e) {
                majDestination(e.latlng.lat, e.latlng.lng);

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
            shadowUrl: '../assets/vendor/leaflet/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        markerDst = L.marker([lat, lng], {
                icon: greenIcon,
                draggable: editable
            }).addTo(map)
            .bindPopup(`<b>Destination : ${labelDst}</b>`);

        if (editable) {
            markerDst.on('dragend', function() {
                const pos = markerDst.getLatLng();
                majDestination(pos.lat, pos.lng);
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
                majAdresse(data.display_name);
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
                majDestination(parseFloat(res.lat), parseFloat(res.lon));
                majAdresse(res.display_name);

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
