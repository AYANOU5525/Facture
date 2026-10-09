<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Scanner FactuPro</title>
    <link rel="stylesheet" href="../assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --bg: #0f0f1a;
            --card: #1a1a2e;
            --text-muted: #9ca3af;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: #fff;
            font-family: 'Inter', -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            padding: 16px 20px;
            text-align: center;
            border-bottom: 1px solid #2d2d44;
        }
        .topbar h1 { font-size: 1.1rem; margin: 0 0 4px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .entreprise-name { color: var(--text-muted); font-size: 0.85rem; }
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            gap: 18px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #1e293b;
        }
        .status-badge.ok { color: var(--success); }
        .status-badge.err { color: var(--danger); }
        #scan-reader {
            width: 100%;
            max-width: 340px;
            border-radius: 16px;
            overflow: hidden;
            background: #000;
            display: none;
        }
        .btn-scan {
            width: 100%;
            max-width: 340px;
            padding: 22px;
            font-size: 1.2rem;
            font-weight: 700;
            border: none;
            border-radius: 16px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-scan:active { transform: scale(0.98); }
        .btn-scan:disabled { opacity: 0.5; }
        .result-card {
            width: 100%;
            max-width: 340px;
            padding: 18px;
            border-radius: 14px;
            background: var(--card);
            display: none;
        }
        .result-card.show { display: block; }
        .result-card .name { font-size: 1.15rem; font-weight: 700; margin-bottom: 6px; }
        .result-card .price { color: var(--success); font-weight: 700; font-size: 1.05rem; }
        .result-card .stock { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }
        .result-card.error .name { color: var(--danger); }
        .footer {
            padding: 14px 20px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .expired-box {
            padding: 30px 20px;
            text-align: center;
        }
        .expired-box i { font-size: 2.4rem; color: var(--warning); margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

    <div class="topbar">
        <h1><i class="fas fa-barcode"></i> Scanner FactuPro</h1>
        <div class="entreprise-name" id="entreprise-name">Connexion à la session…</div>
    </div>

    <div class="main" id="main-area">
        <div class="status-badge" id="status-badge">
            <i class="fas fa-circle-notch fa-spin"></i> <span id="status-text">Connexion…</span>
        </div>
    </div>

    <div class="footer" id="footer" style="display:none;">
        Session valide pendant : <strong id="countdown">--:--</strong>
    </div>

<script src="../assets/vendor/html5-qrcode/html5-qrcode.min.js"></script>
<script>
const TOKEN = <?= json_encode($token) ?>;
const STORAGE_KEY = 'factupro_scan_device_' + TOKEN;
let deviceToken = sessionStorage.getItem(STORAGE_KEY) || '';
let sessionMode = 'produit';
let expiresAt = null;
let countdownTimer = null;
let scanner = null;
let scanLocked = false;

const statusBadge = document.getElementById('status-badge');
const statusText  = document.getElementById('status-text');
const mainArea    = document.getElementById('main-area');
const entrepriseEl = document.getElementById('entreprise-name');
const footer = document.getElementById('footer');

function setStatus(kind, text) {
    statusBadge.className = 'status-badge ' + kind;
    statusText.textContent = text;
}

function fail(message) {
    setStatus('err', 'Erreur');
    mainArea.innerHTML = `
        <div class="expired-box">
            <i class="fas fa-exclamation-triangle"></i>
            <p>${message}</p>
        </div>`;
    footer.style.display = 'none';
    if (countdownTimer) clearInterval(countdownTimer);
}

function startCountdown(seconds) {
    expiresAt = Date.now() + seconds * 1000;
    footer.style.display = 'block';
    if (countdownTimer) clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
        const remaining = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        document.getElementById('countdown').textContent = `${m}:${s}`;
        if (remaining <= 0) {
            clearInterval(countdownTimer);
            stopScanner();
            fail("Cette session de scan a expiré. Veuillez générer un nouveau QR Code depuis le PC.");
        }
    }, 1000);
}

function joinSession() {
    if (!TOKEN) {
        fail('Session de scan invalide.');
        return;
    }
    fetch(`../api/scan_session.php?action=join&token=${encodeURIComponent(TOKEN)}&device=${encodeURIComponent(deviceToken)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                fail(data.message || 'Session de scan invalide.');
                return;
            }
            deviceToken = data.device_token;
            sessionMode = data.mode || 'produit';
            sessionStorage.setItem(STORAGE_KEY, deviceToken);
            entrepriseEl.textContent = 'Entreprise : ' + data.entreprise;
            setStatus('ok', 'Session connectée');
            startCountdown(data.expires_in);
            renderScanUI();
        })
        .catch(() => fail('Impossible de communiquer avec FactuPro.'));
}

function renderScanUI() {
    // Champ manuel toujours disponible : la caméra (getUserMedia) exige un contexte sécurisé
    // (HTTPS, ou littéralement "localhost"), sur un réseau local en HTTP, le navigateur du
    // téléphone la bloque silencieusement. La saisie manuelle garantit que le relais reste
    // utilisable même sans HTTPS configuré.
    mainArea.innerHTML = `
        <div class="status-badge ok"><i class="fas fa-check-circle"></i> Session connectée</div>
        <div id="scan-reader"></div>
        <button class="btn-scan" id="btn-scan"><i class="fas fa-camera"></i> Scanner un code-barre</button>
        <p id="camera-error" style="display:none; color:var(--warning); font-size:0.82rem; max-width:340px; margin:-8px 0 0;"></p>
        <div style="width:100%; max-width:340px; display:flex; gap:8px;">
            <input type="text" id="manual-barcode-input" placeholder="Ou saisir le code manuellement…"
                   style="flex:1; padding:12px 14px; border-radius:10px; border:1px solid #2d2d44; background:#1a1a2e; color:#fff; font-size:0.95rem;">
            <button class="btn-scan" id="btn-manual-send" style="width:auto; max-width:none; padding:12px 18px;"><i class="fas fa-paper-plane"></i></button>
        </div>
        <div class="result-card" id="result-card"></div>
    `;
    document.getElementById('btn-scan').addEventListener('click', toggleCamera);
    document.getElementById('btn-manual-send').addEventListener('click', sendManualBarcode);
    document.getElementById('manual-barcode-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); sendManualBarcode(); }
    });
}

function sendManualBarcode() {
    const input = document.getElementById('manual-barcode-input');
    const barcode = input.value.trim();
    if (!barcode) return;
    input.value = '';
    onBarcodeDetected(barcode);
}

function showCameraError(message) {
    const el = document.getElementById('camera-error');
    if (!el) return;
    el.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i>';
    el.append(message);
    el.style.display = 'block';
}

function toggleCamera() {
    const reader = document.getElementById('scan-reader');
    const btn = document.getElementById('btn-scan');
    if (scanner) {
        stopScanner();
        reader.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-camera"></i> Scanner un produit';
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showCameraError("Caméra indisponible sur cette connexion (HTTP non sécurisé). Utilisez le champ de saisie manuelle ci-dessous, ou demandez au propriétaire d'activer HTTPS.");
        return;
    }
    reader.style.display = 'block';
    btn.innerHTML = '<i class="fas fa-stop"></i> Arrêter';
    scanner = new Html5Qrcode('scan-reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 12, qrbox: { width: 260, height: 120 } },
        (decodedText) => onBarcodeDetected(decodedText),
        () => {}
    ).catch(() => {
        reader.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-camera"></i> Scanner un code-barre';
        showCameraError("Impossible d'accéder à la caméra. Vérifiez les autorisations du navigateur, ou utilisez le champ de saisie manuelle.");
    });
}

function stopScanner() {
    if (!scanner) return;
    const s = scanner;
    scanner = null;
    Promise.resolve().then(() => s.stop()).catch(() => {}).finally(() => { try { s.clear(); } catch (e) {} });
}

function onBarcodeDetected(barcode) {
    if (scanLocked) return; // anti double-scan : ignore les détections répétées pendant le traitement
    scanLocked = true;

    if (scanner) scanner.pause(true);

    const body = new URLSearchParams({ action: 'scan', token: TOKEN, device: deviceToken, barcode });
    fetch('../api/scan_session.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => showResult(data))
        .catch(() => showResult({ success: false, message: 'Impossible de communiquer avec FactuPro.' }))
        .finally(() => {
            setTimeout(() => {
                scanLocked = false;
                if (scanner) scanner.resume();
            }, 800); // délai anti-double-scan avant de reprendre la lecture
        });
}

function showResult(data) {
    const card = document.getElementById('result-card');
    if (!card) return;
    card.classList.add('show');

    if (data.error === 'expired' || data.error === 'device_conflict' || data.error === 'invalid') {
        stopScanner();
        fail(data.message);
        return;
    }

    if (!data.success) {
        card.classList.add('error');
        card.innerHTML = `<div class="name"><i class="fas fa-times-circle"></i> ${data.message || 'Produit introuvable.'}</div>`;
        return;
    }

    card.classList.remove('error');

    if (sessionMode === 'texte') {
        card.innerHTML = `
            <div class="name"><i class="fas fa-check-circle" style="color:var(--success);"></i> Code capturé</div>
            <div class="price" style="font-family:monospace;">${escHtml(data.barcode)}</div>
        `;
        return;
    }

    const stockWarning = data.stock_disponible <= 0;
    card.innerHTML = `
        <div class="name"><i class="fas fa-check-circle" style="color:var(--success);"></i> ${escHtml(data.nom_produit)}</div>
        <div class="price">${Math.round(data.prix_conditionnement).toLocaleString('fr-FR')} F</div>
        <div class="stock" style="${stockWarning ? 'color:var(--warning);font-weight:600;' : ''}">
            ${stockWarning ? 'Stock insuffisant' : 'Stock disponible : ' + data.stock_disponible}
        </div>
    `;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

joinSession();
</script>
</body>
</html>
