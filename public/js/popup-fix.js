/**
 * popup-fix.js — non-destructive fix for trapped popups/dropdowns
 * No HTML structure change. Only toggles .popup-open on ancestor
 * scroll containers so their overflow becomes visible while a menu is open.
 * Also patches Bootstrap boundary to viewport for extra safety.
 */
(function () {
    'use strict';

    var SELECTOR_CONTAINER = '.table-responsive, .card, .admin-card, .filter-card, .modal-body';

    function closestContainer(el) {
        if (!el || !el.closest) return null;
        return el.closest(SELECTOR_CONTAINER);
    }

    function addPopupOpen(el) {
        var c = closestContainer(el);
        if (c) c.classList.add('popup-open');
        // Also mark table-responsive inside card if needed
        if (c && c.classList.contains('table-responsive')) {
            var card = c.closest('.card, .admin-card');
            if (card) card.classList.add('popup-open');
        }
    }

    function removePopupOpen(el) {
        var c = closestContainer(el);
        if (c) {
            // Delay removal to let Bootstrap animation finish and to avoid
            // flicker when switching between menus
            setTimeout(function () {
                // Only remove if no other open menu inside
                if (!c.querySelector('.dropdown-menu.show') &&
                    !c.querySelector('.colhdr-pop:not([hidden])') &&
                    !c.querySelector('.inst-list.open')) {
                    c.classList.remove('popup-open');
                }
                var card = c.closest ? c.closest('.card, .admin-card') : null;
                if (card && !card.querySelector('.dropdown-menu.show')) {
                    card.classList.remove('popup-open');
                }
            }, 180);
        }
    }

    // Bootstrap dropdown events (Bootstrap 5)
    document.addEventListener('show.bs.dropdown', function (e) {
        // e.target is the .dropdown element, e.relatedTarget is the toggle
        var dropdown = e.target;
        var toggle = e.relatedTarget || dropdown.querySelector('[data-bs-toggle="dropdown"]');
        addPopupOpen(toggle || dropdown);
    });
    document.addEventListener('hide.bs.dropdown', function (e) {
        var dropdown = e.target;
        var toggle = e.relatedTarget || dropdown.querySelector('[data-bs-toggle="dropdown"]');
        removePopupOpen(toggle || dropdown);
    });
    // Fallback for programmatic show/hide via class mutation
    document.addEventListener('shown.bs.dropdown', function (e) { addPopupOpen(e.target); });
    document.addEventListener('hidden.bs.dropdown', function (e) { removePopupOpen(e.target); });

    // Custom popups: colhdr-pop (column header filter)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.colhdr-btn');
        if (btn) {
            // Clicking the funnel toggles the pop; delay check until hidden toggled
            setTimeout(function () {
                var pop = btn.parentElement ? btn.parentElement.querySelector('.colhdr-pop') : null;
                var container = closestContainer(btn);
                if (!container || !pop) return;
                if (!pop.hidden) addPopupOpen(btn);
                else removePopupOpen(btn);
            }, 0);
            return;
        }
        // Click outside colhdr should close and release overflow
        if (!e.target.closest('.colhdr-pop') && !e.target.closest('.colhdr-btn')) {
            document.querySelectorAll('.table-responsive.popup-open').forEach(function (c) {
                if (!c.querySelector('.colhdr-pop:not([hidden])') && !c.querySelector('.dropdown-menu.show')) {
                    c.classList.remove('popup-open');
                }
            });
        }
    });

    // Custom popup: inst-list (searchable institute dropdown)
    var instObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            var target = m.target;
            if (target.classList && target.classList.contains('inst-list')) {
                var container = closestContainer(target);
                if (!container) return;
                if (target.classList.contains('open')) addPopupOpen(target);
                else removePopupOpen(target);
            }
        });
    });
    // Observe future inst-list elements
    function observeInstLists() {
        document.querySelectorAll('.inst-list').forEach(function (el) {
            if (el._popupFixObserved) return;
            el._popupFixObserved = true;
            instObserver.observe(el, { attributes: true, attributeFilter: ['class'] });
            // Also watch for display changes via inline style if any
            if (el.classList.contains('open')) addPopupOpen(el);
        });
    }
    document.addEventListener('DOMContentLoaded', observeInstLists);
    document.addEventListener('loadPage', observeInstLists);
    // Poll once in case dynamically injected
    setInterval(observeInstLists, 1000);

    // Generic fallback: watch for any .dropdown-menu.show appearing
    var menuObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.type === 'attributes' && m.attributeName === 'class') {
                var el = m.target;
                if (el.classList.contains('dropdown-menu')) {
                    var container = closestContainer(el);
                    if (!container) return;
                    if (el.classList.contains('show')) addPopupOpen(el);
                    else removePopupOpen(el);
                }
            }
        });
    });
    menuObserver.observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['class'] });

    // Optional: set Bootstrap default boundary to viewport to reduce clippingParents effect
    // Do not override if user explicitly set data-bs-boundary
    document.addEventListener('DOMContentLoaded', function () {
        if (window.bootstrap && bootstrap.Dropdown && bootstrap.Dropdown.Default) {
            // Keep default but ensure viewport is used when no custom boundary
            // We don't globally override to avoid breaking other layouts; instead
            // set via JS on each dropdown that is inside a table-responsive
            document.querySelectorAll(SELECTOR_CONTAINER + ' [data-bs-toggle="dropdown"]').forEach(function (toggle) {
                if (!toggle.getAttribute('data-bs-boundary')) {
                    toggle.setAttribute('data-bs-boundary', 'viewport');
                }
            });
        }
    });

    // Fixed-position containment fix (non-destructive):
    // ANY .modal inside a transformed ancestor (main.content, .admin-card, .card)
    // gets its position:fixed contained to that card and appears "under" the glass.
    // Move every modal to <body> so backdrop/modal are siblings at viewport level.
    function relocateModals() {
        // 1) Known IDs (back-compat)
        var ids = ['photoCropModal', 'editStudentModal'];
        ids.forEach(function (id) {
            var m = document.getElementById(id);
            if (m && m.parentElement !== document.body) {
                document.body.appendChild(m);
            }
        });
        // 2) Generic: any .modal not already direct child of <body>
        document.querySelectorAll('.modal').forEach(function (m) {
            if (m.parentElement !== document.body) {
                // Keep Bootstrap's data/ARIA intact — just re-parent
                document.body.appendChild(m);
            }
        });
    }
    document.addEventListener('DOMContentLoaded', relocateModals);
    document.addEventListener('loadPage', function () { setTimeout(relocateModals, 100); });
    // Watch for dynamically injected modals (Monetix.loadPage swaps main.content)
    var modalRelocateObserver = new MutationObserver(function (mutations) {
        var needs = false;
        mutations.forEach(function (mu) {
            mu.addedNodes && mu.addedNodes.forEach(function (n) {
                if (n.nodeType === 1 && (n.classList.contains('modal') || n.querySelector && n.querySelector('.modal'))) {
                    needs = true;
                }
            });
        });
        if (needs) setTimeout(relocateModals, 20);
    });
    modalRelocateObserver.observe(document.documentElement, { childList: true, subtree: true });
    // Run immediately in case DOM already loaded
    relocateModals();

    /* ===================== Glass backdrop trap fix (non-destructive) =====================
       Problem: frequent open/close on same page leaves orphan .modal-backdrop
       nodes or body.modal-open lock, so next popup appears "under" the glass
       and the full page stays blurred/blocked.
       Fix: collapse duplicate backdrops, force modal above backdrop, clean on hide
       and on seamless navigation. No HTML/layout change. */
    function isModalActuallyVisible(m) {
        if (!m || !m.classList.contains('show')) return false;
        var cs = window.getComputedStyle(m);
        return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05;
    }
    function cleanOrphanBackdrops() {
        var allMods = document.querySelectorAll('.modal.show');
        var hasVisible = false;
        allMods.forEach(function (m) { if (isModalActuallyVisible(m)) hasVisible = true; });
        var hasOpen = hasVisible;
        var backs = document.querySelectorAll('.modal-backdrop');
        if (!hasOpen) {
            backs.forEach(function (b) { b.parentNode && b.parentNode.removeChild(b); });
            // Hide any stale .modal.show that lost visibility but kept class
            allMods.forEach(function (m) {
                m.classList.remove('show');
                m.style.display = 'none';
                m.setAttribute('aria-hidden', 'true');
                if (window.bootstrap && bootstrap.Modal) {
                    try { var inst = bootstrap.Modal.getInstance(m); if (inst) inst.hide(); } catch(e) {}
                }
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.documentElement.style.removeProperty('overflow');
            // Release Bootstrap's scrollbar compensation
            document.body.style.removeProperty('margin-right');
        } else if (backs.length > 1) {
            // frequent triggers created stacked backdrops — keep only the last visible
            for (var i = 0; i < backs.length - 1; i++) {
                backs[i].parentNode && backs[i].parentNode.removeChild(backs[i]);
            }
        }
        // Ensure visible modal is not trapped behind backdrop — force to top
        document.querySelectorAll('.modal.show').forEach(function (m) {
            if (isModalActuallyVisible(m)) {
                m.style.zIndex = '1055';
                // Re-append to body ensures it is above every backdrop node order-wise
                if (m.parentElement !== document.body) document.body.appendChild(m);
                m.style.display = 'block';
            }
        });
        document.querySelectorAll('.modal-backdrop.show').forEach(function (b) {
            b.style.zIndex = '1040';
        });
        // If modal visible but body lost modal-open (race), restore lock correctly
        if (hasOpen && !document.body.classList.contains('modal-open')) {
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function syncBodyLock() {
        var hasOpen = !!document.querySelector('.modal.show');
        if (!hasOpen) {
            // Bootstrap's hide animation is ~300ms; retry to catch delayed removal
            setTimeout(cleanOrphanBackdrops, 50);
            setTimeout(cleanOrphanBackdrops, 320);
            setTimeout(cleanOrphanBackdrops, 600);
        }
    }

    // Hook Bootstrap modal lifecycle
    document.addEventListener('show.bs.modal', function (e) {
        // Before showing a new one, hide any stale visible modal — prevents stacking
        // when user spams same-page trigger (e.g. Edit student repeatedly)
        document.querySelectorAll('.modal.show').forEach(function (m) {
            if (m !== e.target && isModalActuallyVisible(m)) {
                try { var inst = bootstrap.Modal.getInstance(m); if (inst) inst.hide(); } catch(err){}
            }
        });
        // Ensure modal is already in body before Bootstrap computes position
        if (e.target && e.target.parentElement !== document.body) {
            document.body.appendChild(e.target);
        }
    });
    document.addEventListener('shown.bs.modal', function () {
        // Ensure only one glass layer is visible
        var backs = document.querySelectorAll('.modal-backdrop');
        if (backs.length > 1) {
            for (var i = 0; i < backs.length - 1; i++) {
                backs[i].style.opacity = '0';
                backs[i].style.pointerEvents = 'none';
            }
        }
        // Disable transform containment while modal visible
        document.body.classList.add('modal-open');
    });
    document.addEventListener('hidden.bs.modal', syncBodyLock);
    document.addEventListener('hide.bs.modal', function () {
        // allow animation to finish before cleanup
        setTimeout(syncBodyLock, 10);
    });

    // Escape key or backdrop click that dismisses must also unlock page
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
            setTimeout(syncBodyLock, 50);
        }
    });

    // Seamless navigation (Monetix.loadPage) may swap <main> while a modal was
    // logically closed but backdrop remains in <body> — purge it.
    document.addEventListener('loadPage', function () { setTimeout(cleanOrphanBackdrops, 100); });
    window.addEventListener('popstate', function () { setTimeout(cleanOrphanBackdrops, 150); });

    // Periodic orphan sweep — catches cases where Bootstrap's transitionend never
    // fired because tab was backgrounded during frequent actions.
    setInterval(function () {
        if (!document.querySelector('.modal.show') && document.querySelector('.modal-backdrop')) {
            cleanOrphanBackdrops();
        }
    }, 1500);

    // Final safety: if page starts with orphan (e.g. hard refresh while open)
    document.addEventListener('DOMContentLoaded', function () { setTimeout(cleanOrphanBackdrops, 300); });

})();
