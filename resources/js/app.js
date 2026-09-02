import './bootstrap';

/**
 * Global UID copy helper — used by <x-uid-with-copy /> component.
 */
window.copyToClipboard = function copyToClipboard(text, button) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            const original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            setTimeout(() => {
                button.innerHTML = original;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }).catch(() => {
            fallbackCopy(text, button);
        });
    } else {
        fallbackCopy(text, button);
    }

    function fallbackCopy(val, btn) {
        const input = document.createElement('input');
        input.value = val;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(input);
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    }
};
