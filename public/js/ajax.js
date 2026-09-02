(function (window) {
    'use strict';

    var Monetix = window.Monetix || (window.Monetix = {});

    // Read the CSRF token from the meta tag rendered by the layout.
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    Monetix.csrfToken = csrfToken;

    /**
     * Fetch wrapper that speaks the app's JSON response standard:
     *   { success, message, data, errors }
     *
     * Automatically attaches the CSRF token for state-changing methods and
     * maps HTTP status codes to friendly outcomes. Never exposes raw server
     * errors. On a dead session it redirects to login.
     */
    // Non-destructive guard: prevent accidental stringification of DOM element -> "[object HTMLInputElement]"
    function isBadUrl(url) {
        if (!url) return false;
        var s = String(url);
        return s.indexOf('[object') !== -1 || s.indexOf('%5Bobject') !== -1;
    }

    Monetix.request = function (url, options) {
        if (isBadUrl(url)) {
            console.error('[Monetix] Blocked request with stringified object URL:', url);
            Monetix.toast('Invalid request — please refresh and try again.', 'danger');
            return Promise.resolve({ success: false, message: 'Invalid request.' });
        }
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        var headers = options.headers || {};
        var body = options.body;

        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
            headers['X-CSRF-TOKEN'] = csrfToken();
        }
        if (!headers['Accept']) {
            headers['Accept'] = 'application/json';
        }

        var isFormData = typeof FormData !== 'undefined' && body instanceof FormData;
        if (body && !isFormData && typeof body !== 'string') {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        return fetch(url, {
            method: method,
            headers: headers,
            body: body,
            credentials: 'same-origin',
            signal: options.signal || undefined,
        })
        .then(function (response) {
            if (response.status === 401 || response.status === 419) {
                var login = document.querySelector('meta[name="login-url"]');
                window.location.href = (login ? login.getAttribute('content') : '/login');
                return { success: false, message: 'Your session has expired. Please sign in again.' };
            }
            return response.json().catch(function () {
                return { success: false, message: 'Something went wrong. Please try again.' };
            })
            .then(function (data) {
                data = data || {};
                data._status = response.status;
                if (!response.ok && !data.success) {
                    data.success = false;
                    if (response.status === 422) {
                        data.message = data.message || 'Please correct the highlighted fields.';
                    } else if (response.status === 403) {
                        data.message = data.message || 'You are not authorized to perform this action.';
                    } else if (response.status === 404) {
                        data.message = data.message || 'The requested resource was not found.';
                    } else if (response.status === 429) {
                        data.message = data.message || 'Too many requests. Please try again later.';
                    } else if (response.status >= 500) {
                        data.message = data.message || 'Something went wrong. Please try again.';
                    } else {
                        data.message = data.message || 'Request failed. Please try again.';
                    }
                }
                return data;
            });
        })
        .catch(function () {
            return { success: false, message: 'Network error. Please check your connection and try again.', errors: {} };
        });
    };

    /**
     * Delegated handler for single-button status/action forms (approve, reject,
     * suspend, reactivate…). Submits via fetch and refreshes the page content,
     * showing the server message as a toast.
     */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.getAttribute('data-ajax-action') !== '1') { return; }
        e.preventDefault();
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { return; }
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading(submitBtn);
        Monetix.request(form.getAttribute('action'), { method: 'POST', body: new FormData(form) })
            .then(function (res) {
                if (restore) { restore(); }
                if (res && res.success === false) {
                    Monetix.toast(res.message || 'Action failed. Please try again.', 'danger');
                    return;
                }
                Monetix.toast(res && res.message, 'success');
                Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false });
            });
    });

    /**
     * Delegated handler for destructive row actions. Forms tagged with
     * data-ajax-delete submit via fetch and, on success, refresh the current
     * page content through loadPage so the row disappears without a reload.
     */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.getAttribute('data-ajax-delete') !== '1') { return; }
        e.preventDefault();
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { return; }
        Monetix.request(form.action, { method: 'DELETE' })
            .then(function (res) {
                if (res && res.success === false) {
                    Monetix.toast(res.message || 'Action failed. Please try again.', 'danger');
                    return;
                }
                Monetix.toast(res && res.message, 'success');
                Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false });
            });
    });

    /**
     * Content-level seamless navigation. Fetches a full page and swaps only the
     * <main class="content"> node plus the page's own scripts, so tables,
     * search and pagination update without a browser reload. Falls back to a
     * normal navigation if the fetch fails.
     */
    Monetix.loadPage = function (url, opts) {
        if (isBadUrl(url)) {
            console.error('[Monetix] Blocked loadPage with stringified object URL:', url);
            Monetix.toast('Invalid navigation — please refresh.', 'danger');
            return;
        }
        opts = opts || {};
        var main = document.querySelector('main.content');
        if (!main) { window.location.href = url; return; }

        // Remember what the user was interacting with so we can restore focus
        // (and cursor position) after the content swap.
        var focus = null;
        if (opts.preserveFocus) {
            var active = document.activeElement;
            if (active && main.contains(active) && (active.tagName === 'INPUT' || active.tagName === 'SELECT' || active.tagName === 'TEXTAREA')) {
                focus = {
                    name: active.getAttribute('name'),
                    id: active.id,
                    value: active.value,
                    pos: active.tagName === 'INPUT' ? active.selectionStart : null,
                };
            }
        }

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
        })
        .then(function (response) {
            if (response.status === 401 || response.status === 419) {
                var login = document.querySelector('meta[name="login-url"]');
                window.location.href = (login ? login.getAttribute('content') : '/login');
                return null;
            }
            return response.text();
        })
        .then(function (html) {
            if (html === null) { return; }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var newMain = doc.querySelector('main.content');
            if (!newMain) {
                window.location.href = url;
                return;
            }
            var loader = newMain.querySelector('#monetixSkeletonLoader');
            if (loader) { loader.parentNode.removeChild(loader); }

            main.innerHTML = newMain.innerHTML;
            if (doc.title) { document.title = doc.title; }

            // Non-destructive fix: sync CSRF token — previously loadPage only swapped
            // <main>, leaving sidebar logout form with stale token → 419 on next POST.
            try {
                var newMeta = doc.querySelector('meta[name="csrf-token"]');
                if (newMeta) {
                    var newToken = newMeta.getAttribute('content');
                    var curMeta = document.querySelector('meta[name="csrf-token"]');
                    if (curMeta && newToken) curMeta.setAttribute('content', newToken);
                    if (newToken) {
                        document.querySelectorAll('input[name="_token"]').forEach(function (el) {
                            el.value = newToken;
                        });
                    }
                }
            } catch (e) {}

            // Re-run the target page's own scripts (column toggles, modals, …).
            Monetix.runPageScripts(doc);

            // Notify page-level enhancers (geo-select, re-usable address
            // components, …) that the content was swapped so they can bind
            // the freshly rendered nodes.
            if (typeof CustomEvent === 'function') {
                document.dispatchEvent(new CustomEvent('loadPage'));
            }

            if (opts.push !== false) {
                try { history.pushState({ mtx: true, url: url }, '', url); } catch (e) {}
            }

            var switcher = document.querySelector('[data-tab-switch]');
            if (switcher) {
                switcher.querySelectorAll('a[href]').forEach(function (a) {
                    a.classList.toggle('active', a.getAttribute('href') === url);
                });
            }

            // Re-bind AJAX-enhancements present in the freshly loaded content.
            if (Monetix.bindAjaxTables) { Monetix.bindAjaxTables(); }

            main.classList.remove('seamless-fade');
            void main.offsetWidth;
            main.classList.add('seamless-fade');

            // Restore focus + cursor into the same field after the swap.
            if (focus && opts.preserveFocus) {
                var target = null;
                var root = document.querySelector('main.content');
                if (root) {
                    if (focus.id && document.getElementById(focus.id)) { target = document.getElementById(focus.id); }
                    else {
                        target = root.querySelector('input[name="' + focus.name + '"], select[name="' + focus.name + '"], textarea[name="' + focus.name + '"]');
                    }
                }
                if (target) {
                    target.focus();
                    if (target.tagName === 'INPUT' && typeof target.setSelectionRange === 'function' && focus.pos !== null) {
                        try { target.setSelectionRange(focus.pos, focus.pos); } catch (e) {}
                    }
                }
            }

            if (opts.onDone) { opts.onDone(); }
        })
        .catch(function () {
            window.location.href = url;
        });
    };

    var pageScriptsApplied = false;

    /**
     * Replace the current page's inline scripts with the ones coming from the
     * fetched document. The layout wraps @stack('scripts') in #page-scripts;
     * only scripts inside that container are treated as page scripts.
     */
    Monetix.runPageScripts = function (doc) {
        var parent = document.getElementById('page-scripts');
        if (!parent) { return; }

        if (!pageScriptsApplied) {
            pageScriptsApplied = true;
            parent.querySelectorAll('script').forEach(function (s) {
                if (!s.hasAttribute('data-ajax-page-script')) {
                    s.setAttribute('data-ajax-page-script', '');
                }
            });
        }

        parent.querySelectorAll('script[data-ajax-page-script]').forEach(function (s) {
            s.parentNode && s.parentNode.removeChild(s);
        });

        var holder = doc.getElementById('page-scripts');
        if (!holder) { return; }

        holder.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            s.setAttribute('data-ajax-page-script', '');
            if (old.src) { s.src = old.src; s.async = false; }
            else { s.textContent = old.textContent; }
            parent.appendChild(s);
        });
    };

    // Loading-state helpers for buttons/inputs.
    Monetix.loading = function (el, busyText, idleHtml) {
        if (!el) { return function () {}; }
        var busy = busyText || 'Saving…';
        var idle = idleHtml !== undefined ? idleHtml : el.innerHTML;
        el.setAttribute('data-ajax-idle', idle);
        el.disabled = true;
        el.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + busy;
        return function restore() {
            el.disabled = false;
            var saved = el.getAttribute('data-ajax-idle');
            el.innerHTML = saved !== null ? saved : idle;
        };
    };

    // Tiny inline toast/status bar (kept minimal; no dependency).
    // Fixed-position toast must be child of <body>, not .content, otherwise
    // transform/will-change on .content traps it inside the card (visual trap).
    Monetix.toast = function (message, type) {
        type = type || 'success';
        var region = document.body;
        var el = document.createElement('div');
        el.className = 'ajax-toast ajax-toast-' + type;
        el.setAttribute('role', 'status');
        el.innerHTML = message;
        region.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('show'); });
        setTimeout(function () {
            el.classList.remove('show');
            setTimeout(function () { el.parentNode && el.parentNode.removeChild(el); }, 300);
        }, 3000);
    };

    // Debounce helper used by search fields.
    Monetix.debounce = function (fn, wait) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait || 300);
        };
    };

    /**
     * Persist-safe delegated listener registry.
     *
     * loadPage() swaps <main> and re-runs the page scripts on every seamless
     * navigation. A raw document.addEventListener('click', …) in a page script
     * would therefore register a NEW listener each render, making buttons fire
     * twice or more. Monetix.delegate() solves this once, for every page:
     *
     *   - Only ONE physical document listener is ever attached per key.
     *   - Every page-script run simply OVERWRITES the stored handler for that
     *     key, so the active closure always references the freshly rendered,
     *     still-attached DOM nodes. No stale-closure bugs, no once-flags.
     *
     * Usage (in a page script, calling it again on every render is safe):
     *   Monetix.delegate('click', '[data-edit-batch]', function (e, btn) { … }, 'batches-edit');
     *
     * The selector may be omitted to match any click (fn receives (e, null)).
     */
    var delegateHandlers = {};
    var delegateBound = {};

    Monetix.delegate = function (event, selector, handler, key) {
        if (typeof handler !== 'function') { return; }
        var id = key || (event + '__' + (selector || '*'));
        delegateHandlers[id] = handler;
        if (delegateBound[id]) { return; }
        delegateBound[id] = true;

        document.addEventListener(event, function (e) {
            var fn = delegateHandlers[id];
            if (!fn) { return; }
            if (!selector) { fn.call(e.target, e, null); return; }
            var target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) { fn.call(target, e, target); }
        });
    };

    // Non-destructive: graceful logout when session/CSRF expired.
    // Native POST /logout would render 419 page. Intercept logout forms,
    // try POST via fetch (with fresh token); on 419 fall back to GET /logout
    // (Route::get('logout')) which never requires CSRF.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.action) return;
        var action = form.getAttribute('action') || form.action || '';
        if (action.indexOf('/logout') === -1) return;
        if (form.getAttribute('data-ajax-action') === '1' || form.getAttribute('data-ajax-delete') === '1') return;
        // Only handle the app's logout forms (POST)
        if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'POST') return;
        e.preventDefault();
        var url = form.action;
        var token = csrfToken();
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: new URLSearchParams(new FormData(form))
        }).then(function (res) {
            if (res.status === 419) {
                // CSRF mismatch → use GET fallback (never needs token)
                window.location.href = url;
                return null;
            }
            if (res.redirected) {
                window.location.href = res.url;
                return null;
            }
            // Normal POST succeeded — follow redirect manually or reload
            return res.text().then(function () {
                // Logout controller redirects to login; fetch won't auto-navigate for POST→302, so go to login-url
                var login = document.querySelector('meta[name="login-url"]');
                window.location.href = (login ? login.getAttribute('content') : '/login');
            });
        }).catch(function () {
            // Network failure → fallback to GET which always works
            window.location.href = url;
        });
    });

})(window);