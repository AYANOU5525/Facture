/*
 * Pagination des listes affichées dans la page (FactuPro).
 *
 * Usage déclaratif : ajouter data-paginate="5" sur le conteneur des éléments (un <tbody>,
 * ou une liste de cartes). Chaque enfant direct est un élément paginé, sauf :
 *   - data-pg-follow : ligne rattachée à l'élément qui la précède (ex. ligne de détail
 *     dépliable d'une commande) — affichée / masquée avec lui ;
 *   - data-pg-ignore : jamais paginé (ex. message « aucun résultat »).
 * data-paginate-always sur le conteneur : la barre reste affichée même s'il n'y a qu'une
 * page (« 1–5 sur 5 », flèches désactivées) ; sinon elle n'apparaît qu'au-delà d'une page.
 *
 * Fonctionne AVEC les recherches et filtres existants des pages : ceux-ci masquent les
 * éléments par style.display = 'none' ou par l'attribut hidden ; le paginateur, lui, n'utilise
 * que la classe .pg-hidden. Il ne pagine que les éléments retenus par le filtre, et revient en
 * page 1 dès que le filtre change (détecté automatiquement, sans modifier les filtres).
 *
 * API : Paginator.showItem(element) affiche la page qui contient cet élément.
 */
(function () {
    'use strict';

    const instances = new Map();

    class Pager {
        constructor(container) {
            this.container = container;
            this.perPage = Math.max(1, parseInt(container.dataset.paginate, 10) || 10);
            this.page = 1;
            this.nav = document.createElement('div');
            this.nav.className = 'pager-soft';
            this.nav.setAttribute('role', 'navigation');
            this.nav.setAttribute('aria-label', 'Pagination');
            this.anchor().after(this.nav);
            this.nav.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (!btn || btn.closest('.disabled')) return;
                e.preventDefault();
                this.go(parseInt(btn.dataset.page, 10), true);
            });

            // Un filtre a changé (style / hidden d'un élément) ou des éléments ont été ajoutés :
            // retour en page 1. Le paginateur ne modifie que des classes, donc pas de boucle.
            // Seuls comptent les changements sur les éléments de la liste eux-mêmes (enfants
            // directs) : une barre de progression animée DANS une ligne (compte à rebours d'une
            // commande urgente) ne doit pas ramener la liste en page 1 à chaque seconde.
            let pending = false;
            this.observer = new MutationObserver((mutations) => {
                const pertinent = mutations.some((m) => (m.type === 'childList'
                    ? m.target === container
                    : m.target.parentElement === container));
                if (!pertinent || pending) return;
                pending = true;
                requestAnimationFrame(() => { pending = false; this.page = 1; this.render(); });
            });
            this.observer.observe(container, { attributes: true, attributeFilter: ['style', 'hidden'], subtree: true, childList: true });
            this.render();
        }

        /** Élément après lequel placer la pagination (hors du tableau pour un <tbody>). */
        anchor() {
            if (this.container.tagName === 'TBODY') {
                const table = this.container.closest('table');
                return table.closest('.table-responsive, .scrollable-list') || table;
            }
            return this.container;
        }

        items() {
            return [...this.container.children].filter((el) => !el.hasAttribute('data-pg-follow') && !el.hasAttribute('data-pg-ignore'));
        }

        followers(item) {
            const list = [];
            for (let el = item.nextElementSibling; el && el.hasAttribute('data-pg-follow'); el = el.nextElementSibling) list.push(el);
            return list;
        }

        static matches(item) {
            return item.style.display !== 'none' && !item.hidden;
        }

        pages() {
            return Math.max(1, Math.ceil(this.items().filter(Pager.matches).length / this.perPage));
        }

        render() {
            const all = this.items();
            const visibles = all.filter(Pager.matches);
            const pages = Math.max(1, Math.ceil(visibles.length / this.perPage));
            this.page = Math.min(Math.max(1, this.page), pages);
            const start = (this.page - 1) * this.perPage;
            const shown = new Set(visibles.slice(start, start + this.perPage));

            all.forEach((item) => {
                const hide = Pager.matches(item) && !shown.has(item);
                item.classList.toggle('pg-hidden', hide);
                this.followers(item).forEach((f) => f.classList.toggle('pg-hidden', hide || !Pager.matches(item)));
            });

            this.renderNav(visibles.length, pages, start);
        }

        renderNav(total, pages, start) {
            const always = this.container.hasAttribute('data-paginate-always') && total > 0;
            if (total <= this.perPage && !always) {
                this.nav.hidden = true;
                this.nav.innerHTML = '';
                return;
            }
            this.nav.hidden = false;
            const end = Math.min(start + this.perPage, total);
            const p = this.page;
            const nums = [];
            for (let n = 1; n <= pages; n++) {
                if (n === 1 || n === pages || Math.abs(n - p) <= 1) nums.push(n);
                else if (nums[nums.length - 1] !== '…') nums.push('…');
            }
            const li = (label, page, { active = false, disabled = false, aria = '' } = {}) =>
                `<li class="page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}">`
                + (disabled || active
                    ? `<span class="page-link"${aria}>${label}</span>`
                    : `<a class="page-link" href="#" data-page="${page}"${aria}>${label}</a>`)
                + '</li>';

            this.nav.innerHTML =
                `<span class="pager-info">${start + 1}–${end} sur ${total}</span>`
                + '<ul class="pagination mb-0">'
                + li('<i class="fas fa-chevron-left"></i>', p - 1, { disabled: p <= 1, aria: ' aria-label="Page précédente"' })
                + nums.map((n) => (n === '…' ? li('…', 0, { disabled: true }) : li(String(n), n, { active: n === p }))).join('')
                + li('<i class="fas fa-chevron-right"></i>', p + 1, { disabled: p >= pages, aria: ' aria-label="Page suivante"' })
                + '</ul>';
        }

        go(page, scroll) {
            this.page = page;
            this.render();
            if (scroll) {
                const top = this.anchor().getBoundingClientRect().top;
                if (top < 70) window.scrollBy({ top: top - 90, behavior: 'smooth' });
            }
        }

        showItem(el) {
            const visibles = this.items().filter(Pager.matches);
            const index = visibles.indexOf(el);
            if (index >= 0) this.go(Math.floor(index / this.perPage) + 1, false);
        }
    }

    function init(root) {
        (root || document).querySelectorAll('[data-paginate]').forEach((c) => {
            if (!instances.has(c)) instances.set(c, new Pager(c));
        });
    }

    window.Paginator = {
        init,
        showItem(el) {
            for (const [container, pager] of instances) {
                if (container.contains(el)) {
                    // Remonter à l'élément paginé (enfant direct du conteneur)
                    let item = el;
                    while (item.parentElement !== container) item = item.parentElement;
                    while (item.hasAttribute('data-pg-follow') && item.previousElementSibling) item = item.previousElementSibling;
                    pager.showItem(item);
                }
            }
        },
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
})();
