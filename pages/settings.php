<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Vérifier si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    die("Accès refusé. Seuls les administrateurs peuvent modifier les paramètres.");
}

$page_title = "Paramètres de l'entreprise";
include '../includes/header.php';
?>
<!-- Leaflet.js (Cartographie interactive) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php
// ID Entreprise
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

$success = '';
$error = '';

// === TRAITEMENT DU FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $nom = $_POST['nom'];
    $adresse = $_POST['adresse'];
    $tel = $_POST['tel'];
    $email = $_POST['email'];
    $nif = $_POST['nif'];
    $intro = $_POST['description'];

    // Nouveaux champs GPS
    $ville = $_POST['ville'] ?? null;
    $region = $_POST['region'] ?? null;
    $lat = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lon = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE Entreprise 
            SET Nom_Entreprise = ?, Adresse_Entreprise = ?, Tel_Entreprise = ?, 
                Email_Entreprise = ?, NIF_Entreprise = ?, Description_Entreprise = ?,
                Ville = ?, Region = ?, Latitude = ?, Longitude = ?
            WHERE Id_Entreprise = ?
        ");
        $stmt->execute([$nom, $adresse, $tel, $email, $nif, $intro, $ville, $region, $lat, $lon, $entreprise_id]);
        $success = "Informations mises à jour avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les infos actuelles
$stmt = $pdo->prepare("SELECT Nom_Entreprise, Email_Entreprise, Tel_Entreprise, Adresse_Entreprise, NIF_Entreprise, Description_Entreprise, Ville, Region, Latitude, Longitude FROM Entreprise WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$ent = $stmt->fetch();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Paramètres de l'Entreprise</h1>
        <p>Ces informations apparaîtront sur vos factures</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label>Nom de l'entreprise *</label>
                <input type="text" 
                       name="nom" 
                       class="form-control" 
                       value="<?= htmlspecialchars($ent['Nom_Entreprise'] ?? '') ?>" 
                       required>
            </div>

            <div class="row" style="display:flex; gap:20px;">
                <div class="form-group" style="flex:1;">
                    <label>Email de contact *</label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           value="<?= htmlspecialchars($ent['Email_Entreprise'] ?? '') ?>" 
                           required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Téléphone *</label>
                    <input type="text" 
                           name="tel" 
                           class="form-control" 
                           value="<?= htmlspecialchars($ent['Tel_Entreprise'] ?? '') ?>" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label>Adresse complète (Rue, Ville, BP...) *</label>
                <textarea name="adresse" 
                          class="form-control" 
                          rows="2" 
                          required><?= htmlspecialchars($ent['Adresse_Entreprise'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Numéro NIF / RC (Identifiant fiscal)</label>
                <input type="text" 
                       name="nif" 
                       class="form-control" 
                       value="<?= htmlspecialchars($ent['NIF_Entreprise'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Description courte (Slogan)</label>
                <textarea name="description" 
                          class="form-control" 
                          rows="2"><?= htmlspecialchars($ent['Description_Entreprise'] ?? '') ?></textarea>
            </div>

            <hr style="margin: 25px 0; border-top: 1px solid var(--zinc-200);">
            <h4 style="margin-bottom: 15px;"><i class="fas fa-map-marker-alt"></i> Localisation Géographique (Réseau B2B)</h4>
            
            <div class="form-group">
                <label>Rechercher votre adresse sur la carte</label>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <input type="text" id="map-search-input" class="form-control" placeholder="Entrez votre ville, rue, pays... (ex: Lomé, Togo)">
                    <button type="button" class="btn btn-primary" onclick="searchAddress()"><i class="fas fa-search"></i> Rechercher</button>
                    <button type="button" class="btn btn-secondary" onclick="geolocaliser()"><i class="fas fa-location-arrow"></i> Me localiser</button>
                </div>
                <div id="search-results" style="display:none; margin-bottom:10px; max-height:150px; overflow-y:auto; border:1px solid var(--zinc-300); border-radius:6px; background:#fff; box-shadow:var(--shadow-md);"></div>
            </div>

            <div style="margin-bottom: 15px; border-radius:12px; overflow:hidden; border:1px solid var(--zinc-300);">
                <div id="settings-map" style="height: 320px; width: 100%; z-index: 1;"></div>
            </div>

            <!-- Champs cachés pour stocker la localisation technique en BDD -->
            <input type="hidden" name="latitude" id="hidden-lat" value="<?= htmlspecialchars($ent['Latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" id="hidden-lng" value="<?= htmlspecialchars($ent['Longitude'] ?? '') ?>">
            <input type="hidden" name="ville" id="hidden-ville" value="<?= htmlspecialchars($ent['Ville'] ?? '') ?>">
            <input type="hidden" name="region" id="hidden-region" value="<?= htmlspecialchars($ent['Region'] ?? '') ?>">

            <div class="alert alert-info" id="selected-address-info" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                <span><strong>Position sélectionnée :</strong> <span id="address-label"><?= htmlspecialchars($ent['Adresse_Entreprise'] ?? 'Aucune adresse sélectionnée') ?></span></span>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:15px;">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>

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
