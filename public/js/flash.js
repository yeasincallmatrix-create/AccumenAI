(function () {
    function dismiss(el) {
        var height = el.scrollHeight;
        el.classList.add('is-collapsing');
        el.style.maxHeight = height + 'px';
        void el.offsetHeight;
        el.style.maxHeight = '0px';
        el.style.paddingTop = '0';
        el.style.paddingBottom = '0';
        el.style.marginTop = '0';
        el.style.marginBottom = '0';
        setTimeout(function () { el.style.opacity = '0'; }, 100);
        setTimeout(function () { el.remove(); }, 720);
    }

    function init() {
        document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
            setTimeout(function () { dismiss(el); }, 3000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();