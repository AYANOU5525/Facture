<?php
if (ob_get_level() === 0) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'FactuPro' ?></title>

    <!-- Fonts (auto-hébergées, hors ligne) -->
    <link rel="stylesheet" href="../assets/vendor/fonts/fonts.css">

    <!-- Préchauffe les connexions vers les services de carte (tuiles OSM, géocodage,
         itinéraire) dès qu'une page est chargée après connexion, pour que la carte parte plus
         vite quand l'utilisateur atteint réellement une page qui l'affiche (Logistique, Paramètres).
         Leaflet lui-même est auto-hébergé (assets/vendor/leaflet), seuls les services de tuiles/
         géocodage/itinéraire restent distants. -->
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="dns-prefetch" href="https://a.tile.openstreetmap.org">
    <link rel="dns-prefetch" href="https://b.tile.openstreetmap.org">
    <link rel="dns-prefetch" href="https://c.tile.openstreetmap.org">
    <link rel="preconnect" href="https://nominatim.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://router.project-osrm.org" crossorigin>
    <link rel="dns-prefetch" href="https://raw.githubusercontent.com">

    <!-- Styles -->
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
    <link rel="stylesheet" href="../assets/css/animations.css?v=<?= @filemtime(__DIR__ . '/../assets/css/animations.css') ?>">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <script src="../assets/js/page-loader.js?v=<?= @filemtime(__DIR__ . '/../assets/js/page-loader.js') ?>"></script>
    <script>
        try {
            if (localStorage.getItem('factupro_sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    </script>
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <?php $current = basename($_SERVER['PHP_SELF']); ?>

        <!-- ============================================================
             SIDEBAR — offcanvas sur mobile/tablette, fixe en colonne à
             partir du breakpoint lg (voir theme.css : @media (min-width: 992px)).
             ============================================================ -->
        <div class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <span class="offcanvas-title" id="appSidebarLabel">Menu</span>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
            </div>
            <div class="offcanvas-body app-sidebar-body">
                <a href="dashboard.php" class="sidebar-brand">
                    <span class="brand-icon d-inline-flex align-items-center justify-content-center">
                        <i class="fas fa-cube"></i>
                    </span>
                    <span>FactuPro<span class="text-primary">.B2B</span></span>
                </a>

                <nav class="sidebar-nav" aria-label="Navigation principale">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link <?= $current === 'dashboard.php' ? 'active' : '' ?>">
                                <i class="fas fa-chart-pie"></i> Tableau de bord
                            </a>
                        </li>
                    </ul>

                    <?php if (peutVoirVentes() || peutCreerVente() || peutVoirFactures() || peutVoirClients()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Ventes</div>
                        <ul class="nav flex-column">
                            <?php if (peutCreerVente()): ?>
                                <li class="nav-item">
                                    <a href="invoice_add.php" class="nav-link <?= $current === 'invoice_add.php' ? 'active' : '' ?>">
                                        <i class="fas fa-cash-register"></i> Nouvelle vente
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirVentes()): ?>
                                <li class="nav-item">
                                    <a href="sales.php" class="nav-link <?= $current === 'sales.php' ? 'active' : '' ?>">
                                        <i class="fas fa-receipt"></i> Ventes
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirFactures()): ?>
                                <li class="nav-item">
                                    <a href="invoices.php" class="nav-link <?= $current === 'invoices.php' ? 'active' : '' ?>">
                                        <i class="fas fa-file-invoice"></i> Factures
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirClients()): ?>
                                <li class="nav-item">
                                    <a href="clients.php" class="nav-link <?= $current === 'clients.php' ? 'active' : '' ?>">
                                        <i class="fas fa-users"></i> Clients
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutVoirProduits() || peutGererStock()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Stocks</div>
                        <ul class="nav flex-column">
                            <?php if (peutVoirProduits()): ?>
                                <li class="nav-item">
                                    <a href="products.php" class="nav-link <?= $current === 'products.php' ? 'active' : '' ?>">
                                        <i class="fas fa-box"></i> Produits
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererStock()): ?>
                                <li class="nav-item">
                                    <a href="approvisionnement.php" class="nav-link <?= $current === 'approvisionnement.php' ? 'active' : '' ?>">
                                        <i class="fas fa-truck-loading"></i> Approvisionnement
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutAccederB2B() || peutGererB2B()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">B2B</div>
                        <ul class="nav flex-column">
                            <?php if (peutAccederB2B()): ?>
                                <li class="nav-item">
                                    <a href="reseau_b2b.php" class="nav-link <?= $current === 'reseau_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-globe"></i> Réseau
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererB2B()): ?>
                                <li class="nav-item">
                                    <a href="commandes_b2b.php" class="nav-link <?= $current === 'commandes_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-comments"></i> Commandes
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="annonces.php" class="nav-link <?= $current === 'annonces.php' ? 'active' : '' ?>">
                                        <i class="fas fa-bullhorn"></i> Annonces
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="notifications_b2b.php" class="nav-link <?= $current === 'notifications_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-bell"></i> Notifications
                                        <span class="notif-badge notif-badge-side" data-notif-badge hidden>0</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutVoirExpeditions()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Logistique</div>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a href="logistique.php" class="nav-link <?= $current === 'logistique.php' ? 'active' : '' ?>">
                                    <i class="fas fa-truck"></i> Livraisons
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutGererEquipe() || peutGererParametres()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Administration</div>
                        <ul class="nav flex-column">
                            <?php if (peutGererEquipe()): ?>
                                <li class="nav-item">
                                    <a href="team.php" class="nav-link <?= $current === 'team.php' ? 'active' : '' ?>">
                                        <i class="fas fa-user-shield"></i> Équipe
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererParametres()): ?>
                                <li class="nav-item">
                                    <a href="settings.php" class="nav-link <?= $current === 'settings.php' ? 'active' : '' ?>">
                                        <i class="fas fa-cog"></i> Paramètres
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- ============================================================
             HEADER SUPÉRIEUR COMPACT — titre de page, notifications, menu utilisateur
             ============================================================ -->
        <header class="app-topbar">
            <button class="btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Ouvrir le menu">
                <i class="fas fa-bars"></i>
            </button>

            <button class="btn-icon d-none d-lg-inline-flex" type="button" id="sidebarToggle" aria-controls="appSidebar" aria-expanded="true" title="Réduire le menu">
                <i class="fas fa-angles-left"></i>
            </button>

            <h1 class="topbar-title"><?= htmlspecialchars($page_title ?? 'FactuPro') ?></h1>

            <div class="topbar-actions">
                <button id="dark-toggle" class="btn btn-icon rounded-pill" title="Mode sombre / clair" aria-label="Basculer le thème">
                    <i class="fas fa-moon" id="dark-icon"></i>
                </button>

                <?php if (peutAccederB2B()): ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-icon position-relative" id="notifBell" title="Notifications B2B"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="notif-badge" data-notif-badge hidden>0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notif-menu" aria-labelledby="notifBell">
                            <div class="notif-menu-head">
                                <div>
                                    <div class="notif-menu-title">Notifications</div>
                                    <div class="notif-menu-sub" id="notifSub">—</div>
                                </div>
                                <button type="button" class="notif-menu-action" id="notifReadAll" hidden>
                                    <i class="fas fa-check-double"></i> Tout marquer comme lu
                                </button>
                            </div>
                            <div id="notifList" class="notif-list">
                                <div class="notif-empty"><span class="spinner-border spinner-border-sm"></span></div>
                            </div>
                            <button type="button" id="notifEnableDesktop" class="notif-menu-desktop" hidden>
                                <i class="fas fa-desktop"></i> Recevoir aussi les alertes sur l'ordinateur
                            </button>
                            <a href="notifications_b2b.php" class="notif-menu-foot">Voir toutes les notifications <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="dropdown">
                    <a href="#" class="topbar-user d-flex align-items-center gap-2" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-circle"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
                        <span class="d-none d-md-inline topbar-username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                        <i class="fas fa-chevron-down d-none d-md-inline" style="font-size:.7rem; opacity:.6;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:240px;">
                        <li class="px-2 py-1">
                            <div class="fw-bold text-truncate"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
                            <span class="badge text-bg-primary"><?= nomRole($_SESSION['role'] ?? '') ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (peutGererEquipe()): ?>
                            <li><a href="team.php" class="dropdown-item"><i class="fas fa-user-shield text-primary me-2"></i>Gestion d'équipe</a></li>
                            <li><a href="settings.php" class="dropdown-item"><i class="fas fa-cog text-secondary me-2"></i>Paramètres</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a href="../includes/logout.php" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notifToasts" aria-live="polite" style="z-index:1090"></div>

        <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/pagination.js?v=<?= @filemtime(__DIR__ . '/../assets/js/pagination.js') ?>"></script>
        <script>
            /* ── Sidebar rétractable (écrans larges) ──
               Replie la barre en colonne d'icônes, état mémorisé dans le navigateur. */
            (function() {
                const root = document.documentElement;
                const btn = document.getElementById('sidebarToggle');
                if (!btn) return;

                // Infobulle = libellé du lien, utile quand seule l'icône est visible.
                document.querySelectorAll('#appSidebar .nav-link').forEach(function(a) {
                    if (!a.title) a.title = a.textContent.trim().replace(/\s+\d+$/, '');
                });

                function sync() {
                    const collapsed = root.classList.contains('sidebar-collapsed');
                    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    btn.title = collapsed ? 'Ouvrir le menu' : 'Réduire le menu';
                    btn.querySelector('i').className = collapsed ? 'fas fa-angles-right' : 'fas fa-angles-left';
                }
                btn.addEventListener('click', function() {
                    const collapsed = root.classList.toggle('sidebar-collapsed');
                    try { localStorage.setItem('factupro_sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
                    sync();
                });
                sync();
            })();
        </script>
        <?php if (peutAccederB2B()): ?>
        <script>
            /* ── Notifications B2B en temps réel ──
               Interroge le serveur toutes les 15 s (60 s si l'onglet est en arrière-plan) :
               compteur sur la cloche et dans le menu, liste déroulante, et pour chaque NOUVELLE
               notification une alerte en bas à droite, un signal sonore, le compteur dans le
               titre de l'onglet et, si l'utilisateur l'a autorisée, une notification du système. */
            (function() {
                const API = '../api/notifications.php?action=poll';
                const KEY = 'factupro_notif_last_id';
                const baseTitle = document.title;
                const listEl = document.getElementById('notifList');
                const toastsEl = document.getElementById('notifToasts');
                const desktopBtn = document.getElementById('notifEnableDesktop');
                let lastSeen = null;
                try { lastSeen = parseInt(localStorage.getItem(KEY) || '', 10); } catch (e) {}
                if (isNaN(lastSeen)) lastSeen = null;

                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                function setBadges(count) {
                    document.querySelectorAll('[data-notif-badge]').forEach(function(b) {
                        b.textContent = count > 99 ? '99+' : String(count);
                        b.hidden = count === 0;
                    });
                    document.title = count > 0 ? '(' + count + ') ' + baseTitle : baseTitle;
                }

                const subEl = document.getElementById('notifSub');
                const readAllBtn = document.getElementById('notifReadAll');

                function renderList(items, count) {
                    subEl.textContent = count > 0
                        ? count + (count > 1 ? ' nouvelles notifications' : ' nouvelle notification')
                        : 'Vous êtes à jour';
                    readAllBtn.hidden = count === 0;
                    if (!items.length) {
                        listEl.innerHTML = `
                            <div class="notif-empty">
                                <span class="notif-empty-icon"><i class="fas fa-bell-slash"></i></span>
                                <span class="fw-semibold">Aucune notification</span>
                                <span>Les commandes et messages B2B apparaîtront ici.</span>
                            </div>`;
                        return;
                    }
                    listEl.innerHTML = items.map((n) => `
                        <a href="${esc(n.Lien)}" class="notif-row ${n.Est_Lue == 0 ? 'is-unread' : ''}" style="--notif-color:${esc(n.Couleur)}">
                            <span class="notif-row-icon"><i class="fas ${esc(n.Icone)}"></i>${n.Est_Lue == 0 ? '<span class="notif-row-dot" aria-label="Non lue"></span>' : ''}</span>
                            <span class="notif-row-body">
                                <span class="notif-row-title" title="${esc(n.Titre_Court || n.Titre)}">${esc(n.Titre_Court || n.Titre)}</span>
                                <span class="notif-row-msg">${esc(n.Message)}</span>
                                <span class="notif-row-time"><i class="far fa-clock"></i> ${esc(n.Temps_Relatif)}</span>
                            </span>
                        </a>`).join('');
                }

                readAllBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    readAllBtn.disabled = true;
                    try { await fetch('../api/notifications.php?action=read_all', { cache: 'no-store' }); } catch (err) {}
                    readAllBtn.disabled = false;
                    poll();
                });

                function beep() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(880, ctx.currentTime);
                        osc.frequency.setValueAtTime(1175, ctx.currentTime + 0.12);
                        gain.gain.setValueAtTime(0.08, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
                        osc.connect(gain).connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.35);
                    } catch (e) {}
                }

                function toast(n) {
                    const el = document.createElement('div');
                    el.className = 'toast notif-toast';
                    el.setAttribute('role', 'status');
                    el.style.setProperty('--notif-color', n.Couleur);
                    el.innerHTML = `
                        <div class="notif-toast-inner">
                            <span class="notif-row-icon"><i class="fas ${esc(n.Icone)}"></i></span>
                            <div class="notif-row-body">
                                <div class="notif-row-top">
                                    <span class="notif-row-title">${esc(n.Titre_Court || n.Titre)}</span>
                                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Fermer"></button>
                                </div>
                                <div class="notif-row-msg">${esc(n.Message)}</div>
                                <a href="${esc(n.Lien)}" class="notif-toast-link">Voir la commande <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>`;
                    toastsEl.appendChild(el);
                    const t = new bootstrap.Toast(el, { delay: 12000 });
                    el.addEventListener('hidden.bs.toast', () => el.remove());
                    t.show();
                }

                function desktop(n) {
                    if (!('Notification' in window) || Notification.permission !== 'granted') return;
                    try {
                        const notif = new Notification(n.Titre_Court || n.Titre, { body: n.Message, tag: 'factupro-' + n.Id_Notification });
                        notif.onclick = () => { window.focus(); window.location.href = n.Lien; };
                    } catch (e) {}
                }

                async function poll() {
                    try {
                        const res = await fetch(API, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (!data.success) return;
                        const items = data.notifications || [];
                        setBadges(data.count || 0);
                        renderList(items, data.count || 0);

                        const maxId = items.reduce((m, n) => Math.max(m, parseInt(n.Id_Notification, 10)), 0);
                        if (lastSeen === null) {
                            lastSeen = maxId; // première visite : pas de rafale d'alertes sur l'historique
                        } else {
                            const fresh = items.filter((n) => parseInt(n.Id_Notification, 10) > lastSeen && n.Est_Lue == 0).reverse();
                            if (fresh.length) {
                                fresh.slice(-3).forEach((n) => { toast(n); desktop(n); });
                                beep();
                            }
                            lastSeen = Math.max(lastSeen, maxId);
                        }
                        try { localStorage.setItem(KEY, String(lastSeen)); } catch (e) {}
                    } catch (e) {}
                }

                // Proposer les alertes système quand le navigateur le permet (HTTPS ou localhost).
                if ('Notification' in window && window.isSecureContext && Notification.permission === 'default') {
                    desktopBtn.hidden = false;
                    desktopBtn.addEventListener('click', () => {
                        Notification.requestPermission().then(() => { desktopBtn.hidden = true; });
                    });
                }

                let timer = null;
                function schedule() {
                    clearTimeout(timer);
                    timer = setTimeout(async () => { await poll(); schedule(); }, document.hidden ? 60000 : 15000);
                }
                document.addEventListener('visibilitychange', () => { if (!document.hidden) { poll(); } schedule(); });
                window.addEventListener('storage', (e) => { if (e.key === KEY && e.newValue) lastSeen = Math.max(lastSeen ?? 0, parseInt(e.newValue, 10)); });
                poll();
                schedule();
            })();
        </script>
        <?php endif; ?>
        <script>
            (function() {
                /* ── Dark Mode Pill Toggle ── */
                const root = document.documentElement;
                const toggle = document.getElementById('dark-toggle');
                const icon = document.getElementById('dark-icon');

                function applyTheme(dark) {
                    root.setAttribute('data-theme', dark ? 'dark' : 'light');
                    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
                    if (icon) {
                        icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
                    }
                }

                const saved = localStorage.getItem('factupro_theme');
                applyTheme(saved === 'dark');

                if (toggle) {
                    toggle.addEventListener('click', function() {
                        const isDark = root.getAttribute('data-bs-theme') === 'dark';
                        applyTheme(!isDark);
                        localStorage.setItem('factupro_theme', !isDark ? 'dark' : 'light');
                    });
                }
            })();
        </script>

        <script>
            /* Message précédé d'une icône Font Awesome. Le texte est inséré comme TEXTE (append),
               jamais comme HTML : un nom de produit ou un message serveur ne peut rien injecter.
               fromPhone ajoute l'icône téléphone (code reçu du scanner mobile). */
            function setIconText(el, icon, text, fromPhone) {
                el.innerHTML = (fromPhone ? '<i class="fas fa-mobile-screen me-1"></i>' : '')
                    + '<i class="fas ' + icon + ' me-1"></i>';
                el.append(text);
            }
        </script>

        <script>
            /* ── Indicateur de chargement générique sur tous les formulaires ──
               À la soumission (rechargement de page ou POST classique), désactive le(s)
               bouton(s) submit et affiche un spinner, pour que l'interface ne paraisse pas
               figée pendant l'attente serveur et éviter les double-soumissions. Écoute en
               phase de bulle (comportement par défaut) : si un onsubmit inline a déjà annulé
               l'envoi (ex. confirm() refusé), e.defaultPrevented est déjà vrai et on ne touche
               à rien.
               Un bouton désactivé n'est plus envoyé avec le formulaire : si le bouton cliqué
               porte un name (ex. name="valider_paiement", testé côté PHP par isset()), on le
               recopie dans un champ caché avant de le désactiver, sinon l'action est perdue. */
            document.addEventListener('submit', function(e) {
                if (e.defaultPrevented) return;
                const form = e.target;
                if (!(form instanceof HTMLFormElement)) return;

                const submitter = e.submitter;
                if (submitter && submitter.name && !submitter.disabled) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitter.name;
                    hidden.value = submitter.value;
                    form.appendChild(hidden);
                }

                form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(function(btn) {
                    if (btn.disabled) return;
                    btn.dataset.loadingOriginalHtml = btn.innerHTML;
                    btn.disabled = true;
                    if (btn.tagName === 'BUTTON') {
                        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> ' + (btn.dataset.loadingText || 'Chargement…');
                    }
                });
            });
        </script>
    <?php endif; ?>

    <main class="app-main fade-in">