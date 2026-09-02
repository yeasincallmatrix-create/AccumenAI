(function (window) {
    'use strict';

    var Monetix = window.Monetix || (window.Monetix = {});

    /**
     * In-page navigation history.
     *
     * AJAX pagination (students, batches, courses, …) updates the list content
     * without a full reload, so the browser's native back button would walk
     * every unrelated history entry. This keeps a per-list stack of the pages a
     * user actually visited (e.g. page 1 → 2 → 5) and lets the back button
     * return to the previous page inside the same list (5 → 2 → 1) before it
     * finally leaves to the source page.
     */

    var KEY = 'monetix_nav_history';
    var MAX = 40;

    function read() {
        try { return JSON.parse(window.sessionStorage.getItem(KEY)) || {}; } catch (e) { return {}; }
    }

    function write(map) {
        try { window.sessionStorage.setItem(KEY, JSON.stringify(map)); } catch (e) {}
    }

    function listKey(pathname) {
        return (pathname || window.location.pathname).replace(/\/$/, '') || '/';
    }

    // Entering a list with a full page load starts a fresh navigation stack, so
    // stale pages from a previous session never resurface.
    function resetCurrent() {
        var map = read();
        map[listKey()] = [];
        write(map);
    }

    Monetix.navHistory = {

        /**
         * Remember that we are navigating from the current URL to `nextUrl`.
         * Scoped per list path so back only walks the pages visited inside that
         * list and never jumps to an unrelated page.
         */
        record: function (nextUrl) {
            var target = null;
            try { target = new URL(nextUrl, window.location.origin); } catch (e) { return; }
            var map = read();
            var stack = map[listKey(target.pathname)] || [];
            var current = window.location.pathname + window.location.search;
            if (stack[stack.length - 1] !== current) {
                stack.push(current);
                if (stack.length > MAX) { stack.shift(); }
                map[listKey(target.pathname)] = stack;
                write(map);
            }
        },

        /**
         * Pop and return the previous page visited in this list, or null when
         * the stack is empty (nothing left inside the list).
         */
        previous: function (pathname) {
            var map = read();
            var stack = map[listKey(pathname)] || [];
            var prev = stack.pop();
            map[listKey(pathname)] = stack;
            write(map);
            return prev || null;
        },

        resetCurrent: resetCurrent,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resetCurrent);
    } else {
        resetCurrent();
    }
})(window);
