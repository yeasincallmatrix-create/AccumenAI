{{-- Full-page loading overlay for the standalone auth/workspace screens.
     Rules live in public/css/pages.css (loaded by both pages); the script
     keeps it up while the document loads and while a form POST is in
     flight, then fades it out. --}}
<div class="loading-screen" id="loadingScreen" role="status" aria-live="polite" aria-busy="true">
    <div class="loading-screen-box">
        <span class="spinner-border text-primary loading-screen-spin" role="status" aria-hidden="true"></span>
        <div class="loading-screen-text" id="loadingScreenText">{{ mawa_e('loading.default') }}</div>
    </div>
</div>
<script>
(function () {
    var el = document.getElementById('loadingScreen');
    if (!el) return;
    var textEl = document.getElementById('loadingScreenText');
    var defaultText = textEl ? textEl.textContent : '';
    var pending = false;
    var safety = null;
    var loadCap = setTimeout(hide, 6000);
    var t0 = Date.now();

    function hide() {
        pending = false;
        if (safety) { clearTimeout(safety); safety = null; }
        if (textEl) textEl.textContent = defaultText;
        el.classList.add('loading-screen-hide');
        el.setAttribute('aria-hidden', 'true');
    }

    function show(msg) {
        pending = true;
        if (textEl) textEl.textContent = msg || defaultText;
        el.classList.remove('loading-screen-hide');
        el.setAttribute('aria-hidden', 'false');
        if (safety) clearTimeout(safety);
        // Nothing navigated (validation, captcha failure, JS error):
        // never trap the page behind the overlay.
        safety = setTimeout(hide, 15000);
        if (loadCap) { clearTimeout(loadCap); loadCap = null; }
    }

    // Initial paint: stay up until every asset has loaded, but not for
    // less than ~450ms so a fast page doesn't flash.
    function onLoaded() {
        setTimeout(function () { if (!pending) hide(); }, Math.max(0, 450 - (Date.now() - t0)));
    }
    if (document.readyState === 'complete') onLoaded();
    else window.addEventListener('load', onLoaded);

    // Login POST, workspace switch, logout … and plain <a> navigations
    // that opt in via data-loading-text (Google OAuth, Add Business …).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        show(form.getAttribute('data-loading-text'));
    }, true);
    document.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a[data-loading-text]') : null;
        if (!link || link.target === '_blank' || !link.href) return;
        show(link.getAttribute('data-loading-text'));
    }, true);

    // A script bailed out and injected an error alert while the overlay was
    // up (recaptcha/timeout failures, 422 re-render) — drop it so the
    // message is reachable. Mutations inside the overlay itself are ignored.
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            if (!pending) return;
            for (var i = 0; i < records.length; i++) {
                var r = records[i];
                var nodes = r.type === 'childList' ? r.addedNodes : [r.target];
                for (var j = 0; j < nodes.length; j++) {
                    var n = nodes[j];
                    if (n.nodeType !== 1 || el.contains(n)) continue;
                    if (n.classList && n.classList.contains('alert')) { hide(); return; }
                    if (n.querySelector && n.querySelector('.alert')) { hide(); return; }
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }
})();
</script>
