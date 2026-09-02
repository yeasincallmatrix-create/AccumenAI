(function () {
    'use strict';

    function makeToggle(input) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.classList.add('btn', 'btn-outline-secondary', 'pw-toggle-btn');
        btn.setAttribute('tabindex', '-1');
        btn.setAttribute('aria-label', input.getAttribute('aria-label') || 'Show password');
        btn.innerHTML = '<i class="bi bi-eye"></i>';
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
        return btn;
    }

    function enhanceScope(root) {
        var inputs = root.querySelectorAll('input[type="password"]');
        for (var i = 0; i < inputs.length; i++) {
            (function (input) {
                if (input.dataset.pwToggle) { return; }
                input.dataset.pwToggle = '1';

                var group = input.closest('.input-group');
                if (group) {
                    group.insertBefore(makeToggle(input), input.nextSibling);
                } else {
                    var wrap = document.createElement('div');
                    wrap.classList.add('input-group', 'input-group-pw');
                    if (input.classList.contains('form-control-sm')) { wrap.classList.add('input-group-sm'); }

                    var spacing = ['mt-0','mt-1','mt-2','mt-3','mt-4','mt-5',
                                   'mb-0','mb-1','mb-2','mb-3','mb-4','mb-5',
                                   'my-0','my-1','my-2','my-3','my-4','my-5'];
                    for (var s = 0; s < spacing.length; s++) {
                        if (input.classList.contains(spacing[s])) {
                            wrap.classList.add(spacing[s]);
                            input.classList.remove(spacing[s]);
                        }
                    }

                    var parent = input.parentNode;
                    parent.insertBefore(wrap, input);
                    wrap.appendChild(input);
                    wrap.appendChild(makeToggle(input));
                }
            })(inputs[i]);
        }
    }

    function enhanceAll() { enhanceScope(document); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAll);
    } else {
        enhanceAll();
    }

    document.addEventListener('loadPage', enhanceAll);
})();