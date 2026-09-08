/**
 * Auto Initial Caps for First Name / Last Name — project-wide.
 *
 * Attaches to every input whose name/id suggests a person name:
 *   first_name, last_name, middle_name, father_name, mother_name,
 *   guardian_name, emergency_contact_name (+ data-auto-caps="name")
 *
 * Behaviour:
 *  - While typing: capitalizes the first letter after each word boundary
 *    (space, hyphen, apostrophe, dot) without moving the caret.
 *  - On blur / form submit: full Initial-Caps normalization
 *    ("RAHIM  uddin" -> "Rahim Uddin", "md. karim-uddin" -> "Md. Karim-Uddin").
 *  - Works for dynamically injected modals (MutationObserver) and
 *    Livewire/Alpine inputs (dispatches input+change after rewrite).
 *  - Non-Latin scripts (Bengali) pass through untouched.
 */
(function () {
    var NAME_RE = /^(first_name|last_name|middle_name|father_name|mother_name|guardian_name|emergency_contact_name|full_name)$/i;
    var ID_RE = /(first[_-]?name|last[_-]?name|father[_-]?name|mother[_-]?name|guardian[_-]?name|emergency[_-]?contact[_-]?name|middle[_-]?name)/i;

    function isNameField(el) {
        if (!el || el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') return false;
        if (el.type === 'hidden' || el.type === 'password' || el.type === 'email' || el.type === 'number') return false;
        if (el.hasAttribute('data-auto-caps')) return el.getAttribute('data-auto-caps') !== 'off';
        var name = (el.getAttribute('name') || '').split('[').pop().replace(']', '');
        if (name && NAME_RE.test(name)) return true;
        var id = el.id || '';
        if (id && ID_RE.test(id)) return true;
        return false;
    }

    function titleWord(word) {
        if (!word) return word;
        // Lowercase rest, upper first (Unicode-safe for Latin; Bengali unchanged)
        var lowered = word.toLocaleLowerCase();
        return lowered.replace(/^([\p{L}])/u, function (m) { return m.toLocaleUpperCase(); });
    }

    function toInitialCaps(value) {
        var collapsed = String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
        if (!collapsed) return collapsed;
        // Split keeping delimiters (space, hyphen, apostrophe, dot, slash, paren)
        return collapsed.split(/([ \t\-'‘’.\/()]+)/).map(function (part) {
            if (/^[ \t\-'‘’.\/()]+$/.test(part) || part === '') return part;
            return titleWord(part);
        }).join('');
    }

    // Live-typing: uppercase letters that start a word, keep caret stable.
    function liveCaps(el) {
        var start = el.selectionStart, end = el.selectionEnd, val = el.value;
        var out = val.replace(/(^|[\s\-'‘’."\/(\[])([a-z\u00E0-\u00FF\u0100-\u017F])/gu, function (m, p1, p2) {
            return p1 + p2.toLocaleUpperCase();
        });
        if (out !== val) {
            el.value = out;
            try { el.setSelectionRange(start, end); } catch (e) {}
            notify(el);
        }
    }

    function finalize(el) {
        var next = toInitialCaps(el.value);
        if (next !== el.value) {
            el.value = next;
            notify(el);
        }
        // Visual hint so even unformatted keystrokes look capitalized.
        el.style.textTransform = 'capitalize';
        if (!el.hasAttribute('autocapitalize')) el.setAttribute('autocapitalize', 'words');
    }

    function notify(el) {
        // Keep Livewire (wire:model) / Alpine / listeners in sync.
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function enhance(el) {
        if (el.__autoCapsBound) return;
        el.__autoCapsBound = true;
        el.style.textTransform = 'capitalize';
        if (!el.hasAttribute('autocapitalize')) el.setAttribute('autocapitalize', 'words');
        // Normalize server-rendered old() values on load (e.g. "rahim" -> "Rahim").
        if (el.value) finalize(el);
        el.addEventListener('input', function () { liveCaps(el); });
        el.addEventListener('blur', function () { finalize(el); });
        var form = el.form;
        if (form && !form.__autoCapsSubmitBound) {
            form.__autoCapsSubmitBound = true;
            form.addEventListener('submit', function () {
                form.querySelectorAll('input, textarea').forEach(function (f) {
                    if (isNameField(f)) finalize(f);
                });
            });
        }
    }

    function scan(root) {
        (root || document).querySelectorAll('input, textarea').forEach(function (el) {
            if (isNameField(el)) enhance(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }

    // Catch modals / ajax tables / Livewire morphs injecting new inputs.
    if ('MutationObserver' in window) {
        var mo = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.matches && n.matches('input, textarea')) {
                        if (isNameField(n)) enhance(n);
                    }
                    if (n.querySelectorAll) {
                        n.querySelectorAll('input, textarea').forEach(function (el) {
                            if (isNameField(el)) enhance(el);
                        });
                    }
                });
            });
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
    }

    // Livewire navigation / morph hooks.
    document.addEventListener('livewire:navigated', function () { scan(document); });
    document.addEventListener('livewire:load', function () { scan(document); });
    if (window.Livewire && window.Livewire.hook) {
        try { window.Livewire.hook('morph.updated', function () { scan(document); }); } catch (e) {}
    }

    window.AutoCaps = { toInitialCaps: toInitialCaps, scan: scan };
})();
