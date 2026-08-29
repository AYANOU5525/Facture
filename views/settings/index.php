<!-- Leaflet.js (Cartographie interactive) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="container fade-in py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="fas fa-cog text-primary"></i> Paramètres de l'Entreprise</h1>
        <p class="text-body-secondary mb-0">Ces informations apparaîtront sur vos factures</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mx-auto" style="max-width: 800px;">
        <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label">Nom de l'entreprise *</label>
                <input type="text"
                       name="nom"
                       class="form-control"
                       value="<?= htmlspecialchars($ent['Nom_Entreprise'] ?? '') ?>"
                       required>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Email de contact *</label>
                    <input type="email"
                           name="email"
                           class="form-control"
                           value="<?= htmlspecialchars($ent['Email_Entreprise'] ?? '') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone *</label>
                    <input type="text"
                           name="tel"
                           class="form-control"
                           value="<?= htmlspecialchars($ent['Tel_Entreprise'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label">Adresse complète (Rue, Ville, BP...) *</label>
                <textarea name="adresse"
                          class="form-control"
                          rows="2"
                          required><?= htmlspecialchars($ent['Adresse_Entreprise'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Numéro NIF / RC (Identifiant fiscal)</label>
                <input type="text"
                       name="nif"
                       class="form-control"
                       value="<?= htmlspecialchars($ent['NIF_Entreprise'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Description courte (Slogan)</label>
                <textarea name="description"
                          class="form-control"
                          rows="2"><?= htmlspecialchars($ent['Description_Entreprise'] ?? '') ?></textarea>
            </div>

            <hr class="my-4">
            <h4 class="fs-6 mb-3"><i class="fas fa-map-marker-alt text-primary"></i> Localisation Géographique (Réseau B2B)</h4>

            <div class="mb-3">
                <label class="form-label">Rechercher votre adresse sur la carte</label>
                <div class="input-group mb-2">
                    <input type="text" id="map-search-input" class="form-control" placeholder="Entrez votre ville, rue, pays... (ex: Lomé, Togo)">
                    <button type="button" class="btn btn-primary" onclick="searchAddress()"><i class="fas fa-search"></i> Rechercher</button>
                    <button type="button" class="btn btn-secondary" onclick="geolocaliser()"><i class="fas fa-location-arrow"></i> Me localiser</button>
                </div>
                <div id="search-results" class="list-group mb-2" style="display:none; max-height:150px; overflow-y:auto;"></div>
            </div>

            <div class="mb-3 rounded-3 overflow-hidden border">
                <div id="settings-map" style="height: 320px; width: 100%; z-index: 1;"></div>
            </div>

            <!-- Champs cachés pour stocker la localisation technique en BDD -->
            <input type="hidden" name="latitude" id="hidden-lat" value="<?= htmlspecialchars($ent['Latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" id="hidden-lng" value="<?= htmlspecialchars($ent['Longitude'] ?? '') ?>">
            <input type="hidden" name="ville" id="hidden-ville" value="<?= htmlspecialchars($ent['Ville'] ?? '') ?>">
            <input type="hidden" name="region" id="hidden-region" value="<?= htmlspecialchars($ent['Region'] ?? '') ?>">

            <div class="alert alert-info d-flex align-items-center gap-2" id="selected-address-info">
                <i class="fas fa-info-circle"></i>
                <span><strong>Position sélectionnée :</strong> <span id="address-label"><?= htmlspecialchars($ent['Adresse_Entreprise'] ?? 'Aucune adresse sélectionnée') ?></span></span>
            </div>

            <button type="submit" class="btn btn-primary mt-2">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </form>
        </div>
    </div>

    <!-- SECTION CHANGEMENT DE MOT DE PASSE -->
    <div class="card mx-auto mt-4" style="max-width:800px;">
        <div class="card-body">
        <h3 class="fs-6 mb-3"><i class="fas fa-shield-alt text-primary"></i> Sécurité — Changer mon mot de passe</h3>

        <?php if ($success_pwd): ?>
            <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check"></i> <?= htmlspecialchars($success_pwd) ?></div>
        <?php endif; ?>
        <?php if ($error_pwd): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_pwd) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="mb-3">
                <label class="form-label">Mot de passe actuel</label>
                <div class="password-wrapper">
                    <input type="password" id="old_password" name="old_password" class="form-control"
                           placeholder="Votre mot de passe actuel" required>
                    <button type="button" class="password-toggle" onclick="togglePwd('old_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           placeholder="Minimum 8 caractères" required
                           oninput="checkPwdStrength(this.value); checkPwdMatch()">
                    <button type="button" class="password-toggle" onclick="togglePwd('new_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="mt-2">
                    <div class="rounded" style="height:5px; background:var(--bs-secondary-bg);">
                        <div id="pwd-strength-fill" class="rounded" style="height:100%; width:0%; transition:all .3s;"></div>
                    </div>
                    <small id="pwd-strength-label" class="text-body-secondary"></small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmer le nouveau mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Répétez le nouveau mot de passe" required
                           oninput="checkPwdMatch()">
                    <button type="button" class="password-toggle" onclick="togglePwd('confirm_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small id="pwd-match-label" class="d-block mt-1"></small>
            </div>

            <button type="submit" class="btn btn-danger">
                <i class="fas fa-lock"></i> Changer le mot de passe
            </button>
        </form>
        </div>
    </div>
</div>

<style>
.password-wrapper { position:relative; display:flex; align-items:center; }
.password-wrapper .form-control { padding-right:46px; }
.password-toggle {
    position:absolute; right:12px; background:none; border:none;
    cursor:pointer; color:var(--text-muted); font-size:1rem; padding:0; transition:color .2s;
}
.password-toggle:hover { color:var(--primary); }
</style>

<script>
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkPwdStrength(val) {
    const fill  = document.getElementById('pwd-strength-fill');
    const label = document.getElementById('pwd-strength-label');
    let score = 0;
    if (val.length >= 8)           score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;
    const levels = [
        { pct:'0%',   color:'#e9ecef', text:'' },
        { pct:'25%',  color:'#dc3545', text:'Très faible' },
        { pct:'50%',  color:'#fd7e14', text:'Faible' },
        { pct:'75%',  color:'#ffc107', text:'Moyen' },
        { pct:'100%', color:'#28a745', text:'Fort' },
    ];
    const l = levels[score];
    fill.style.width      = l.pct;
    fill.style.background = l.color;
    label.textContent     = l.text;
    label.style.color     = l.color;
}

function checkPwdMatch() {
    const p1    = document.getElementById('new_password').value;
    const p2    = document.getElementById('confirm_password').value;
    const label = document.getElementById('pwd-match-label');
    if (!p2) { label.textContent = ''; return; }
    if (p1 === p2) {
        label.textContent = '✔ Les mots de passe correspondent';
        label.style.color = '#28a745';
    } else {
        label.textContent = '✖ Les mots de passe ne correspondent pas';
        label.style.color = '#dc3545';
    }
}
</script>

<script>
let map, marker;
const initialLat = parseFloat(document.getElementById('hidden-lat').value) || 6.1372; // Lomé par défaut
const initialLng = parseFloat(document.getElementById('hidden-lng').value) || 1.2125;

function initMap() {
    map = L.map('settings-map').setView([initialLat, initialLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

    // Événement déplacement du marqueur
    marker.on('dragend', function() {
        const position = marker.getLatLng();
        updateCoords(position.lat, position.lng, true);
    });

    // Événement clic sur la carte
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng, true);
    });
}

async function updateCoords(lat, lng, reverseGeocode = false) {
    document.getElementById('hidden-lat').value = lat;
    document.getElementById('hidden-lng').value = lng;

    if (reverseGeocode) {
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
            const data = await response.json();
            if (data && data.display_name) {
                const address = data.display_name;
                document.getElementById('address-label').textContent = address;

                // Mettre à jour le textarea d'adresse
                const addrInput = document.querySelector('textarea[name="adresse"]');
                if (addrInput) addrInput.value = address;

                // Extraire ville et région/pays
                const city = data.address.city || data.address.town || data.address.village || data.address.suburb || '';
                const region = data.address.state || data.address.region || data.address.country || '';
                document.getElementById('hidden-ville').value = city;
                document.getElementById('hidden-region').value = region;
            }
        } catch (e) {
            console.error("Reverse geocoding error: ", e);
        }
    }
}

async function searchAddress() {
    const query = document.getElementById('map-search-input').value.trim();
    if (!query) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&addressdetails=1&limit=5`);
        const results = await response.json();

        const resultsDiv = document.getElementById('search-results');
        resultsDiv.innerHTML = '';
        if (results && results.length > 0) {
            resultsDiv.style.display = 'block';
            results.forEach(res => {
                const div = document.createElement('div');
                div.style.padding = '8px 12px';
                div.style.cursor = 'pointer';
                div.style.borderBottom = '1px solid #f1f5f9';
                div.style.fontSize = '0.85rem';
                div.textContent = res.display_name;
                div.onclick = function() {
                    const lat = parseFloat(res.lat);
                    const lon = parseFloat(res.lon);
                    map.setView([lat, lon], 15);
                    marker.setLatLng([lat, lon]);

                    document.getElementById('address-label').textContent = res.display_name;
                    const addrInput = document.querySelector('textarea[name="adresse"]');
                    if (addrInput) addrInput.value = res.display_name;

                    const city = res.address.city || res.address.town || res.address.village || res.address.suburb || '';
                    const region = res.address.state || res.address.region || res.address.country || '';
                    document.getElementById('hidden-ville').value = city;
                    document.getElementById('hidden-region').value = region;
                    document.getElementById('hidden-lat').value = lat;
                    document.getElementById('hidden-lng').value = lon;

                    resultsDiv.style.display = 'none';
                };
                resultsDiv.appendChild(div);
            });
        } else {
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div style="padding:8px 12px; color:var(--text-muted); font-size:0.85rem;">Aucun résultat trouvé</div>';
        }
    } catch (e) {
        console.error("Search error: ", e);
    }
}

function geolocaliser() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            map.setView([lat, lng], 15);
            marker.setLatLng([lat, lng]);
            updateCoords(lat, lng, true);
        }, err => {
            alert("Erreur de géolocalisation : " + err.message);
        });
    } else {
        alert("La géolocalisation n'est pas supportée par votre navigateur.");
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', initMap);
</script>

</body>

</html>
