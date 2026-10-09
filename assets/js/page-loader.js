/*
 * Indicateur de chargement des pages (FactuPro).
 *
 * - Barre de progression fine en haut de l'écran : au chargement initial de la page
 *   (jusqu'à l'événement « load ») et dès qu'on quitte la page (clic sur un lien interne,
 *   envoi de formulaire) jusqu'à l'affichage de la suivante.
 * - Si l'attente dépasse 800 ms, un voile léger avec « Chargement… » s'affiche en plus :
 *   rien n'apparaît pour une page rapide, pour éviter un clignotement à chaque clic.
 *
 * Ignorés : liens target="_blank", téléchargements, tel:/mailto:, ancres « # », liens
 * javascript:, clics avec Ctrl/Cmd/Maj/molette, éléments marqués data-no-loader, et
 * formulaires dont l'envoi a été annulé (confirm() refusé, validation JS).
 *
 * API : PageLoader.start() / PageLoader.done() pour une attente déclenchée par script.
 */
(function () {
    'use strict';

    const OVERLAY_DELAY = 800;
    // Filet de sécurité : si la page ne change finalement pas (sortie annulée, lien qui
    // déclenche un téléchargement), l'indicateur ne doit pas rester affiché indéfiniment.
    const SAFETY_TIMEOUT = 15000;
    let bar, overlay, trickle, overlayTimer, safetyTimer, value = 0;

    function build() {
        if (bar) return;
        bar = document.createElement('div');
        bar.className = 'page-loader-bar';
        bar.setAttribute('aria-hidden', 'true');
        overlay = document.createElement('div');
        overlay.className = 'page-loader-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<div class="page-loader-box"><span class="page-loader-spinner"></span><span>Chargement…</span></div>';
        document.documentElement.append(bar, overlay);
    }

    function set(v) {
        value = v;
        bar.style.transform = 'scaleX(' + v + ')';
    }

    function start(withOverlay) {
        build();
        clearInterval(trickle);
        clearTimeout(overlayTimer);
        bar.classList.remove('is-done');
        bar.classList.add('is-active');
        set(Math.max(value, 0.08));
        // Progression qui ralentit sans jamais atteindre 100 % tant que la page n'est pas là
        trickle = setInterval(() => set(value + (0.92 - value) * 0.08), 200);
        if (withOverlay) {
            overlayTimer = setTimeout(() => overlay.classList.add('is-visible'), OVERLAY_DELAY);
        }
        clearTimeout(safetyTimer);
        safetyTimer = setTimeout(done, SAFETY_TIMEOUT);
    }

    function done() {
        if (!bar) return;
        clearInterval(trickle);
        clearTimeout(overlayTimer);
        clearTimeout(safetyTimer);
        overlay.classList.remove('is-visible');
        set(1);
        bar.classList.add('is-done');
        setTimeout(() => {
            bar.classList.remove('is-active', 'is-done');
            set(0);
        }, 350);
    }

    function isNavigationLink(a, e) {
        if (!a || a.hasAttribute('data-no-loader') || a.hasAttribute('download')) return false;
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
        if (a.target && a.target !== '_self') return false;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href)) return false;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin) return false;
        // Même page, simple changement d'ancre : pas de chargement
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
        return true;
    }

    // Chargement initial de la page affichée
    start(false);
    if (document.readyState === 'complete') done();
    else window.addEventListener('load', done);

    // Départ vers une autre page. Écoute en phase de bulle + setTimeout : les gestionnaires
    // propres aux pages (onclick, onsubmit avec confirm()) ont déjà pu annuler l'action.
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        setTimeout(() => { if (isNavigationLink(a, e)) start(true); }, 0);
    });
    document.addEventListener('submit', (e) => {
        const form = e.target;
        setTimeout(() => {
            if (e.defaultPrevented || form.hasAttribute('data-no-loader') || (form.target && form.target !== '_self')) return;
            start(true);
        }, 0);
    });

    // Formulaires envoyés par script (form.submit(), ex. filtres au changement d'une liste) :
    // cette méthode ne déclenche pas l'événement « submit ».
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (!this.hasAttribute('data-no-loader') && (!this.target || this.target === '_self')) start(true);
        return nativeSubmit.call(this);
    };

    // Pour une attente déclenchée par script (ex. appel serveur en arrière-plan)
    window.PageLoader = { start: () => start(true), done };

    // Retour arrière depuis le cache du navigateur : la page est déjà prête
    window.addEventListener('pageshow', (e) => { if (e.persisted) done(); });
})();
