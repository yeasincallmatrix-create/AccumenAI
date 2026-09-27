@if (\App\Services\Auth\RecaptchaService::enabled())
    @if (\App\Services\Auth\RecaptchaService::isV3())
        <script src="https://www.google.com/recaptcha/api.js?render={{ \App\Services\Auth\RecaptchaService::siteKey() }}" async defer></script>
        <script>
        (function () {
            var siteKey = @json(\App\Services\Auth\RecaptchaService::siteKey());
            var attempts = 0;

            function fail(form, reason) {
                console.error('[recaptcha] ' + reason);
                if (document.getElementById('recaptcha-load-error')) return;
                var box = document.createElement('div');
                box.id = 'recaptcha-load-error';
                box.className = 'alert alert-danger py-2 text-start';
                box.textContent = 'Security check could not be loaded. Please reload the page (Ctrl+F5) and try again.';
                form.parentNode.insertBefore(box, form);
                form.removeAttribute('data-recaptcha-pending');
            }

            function whenReady(cb, onTimeout) {
                if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                    window.grecaptcha.ready(function () { cb(false); });
                    return;
                }
                // Script still loading — retry for ~8s, then report the failure.
                if (++attempts < 160) {
                    setTimeout(function () { whenReady(cb, onTimeout); }, 50);
                } else {
                    onTimeout();
                }
            }

            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || form.tagName !== 'FORM') return;
                var input = form.querySelector('input[name="g-recaptcha-response"]');
                if (!input) return;

                // Second submit after the token was injected: let it through.
                if (form.getAttribute('data-recaptcha-pending') === '1') return;

                e.preventDefault();

                whenReady(function (failed) {
                    if (failed) return;
                    if (typeof window.grecaptcha.execute !== 'function') {
                        fail(form, 'grecaptcha.execute is unavailable');
                        return;
                    }
                    window.grecaptcha.execute(siteKey, { action: 'auth' }).then(function (token) {
                        if (!token) {
                            fail(form, 'empty token returned');
                            return;
                        }
                        input.value = token;
                        form.setAttribute('data-recaptcha-pending', '1');
                        HTMLFormElement.prototype.submit.call(form);
                    }).catch(function (err) {
                        fail(form, 'execute rejected: ' + (err && err.message ? err.message : err));
                    });
                }, function () { fail(form, 'api.js never loaded'); });
            }, true);
        })();
        </script>
    @else
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@endif
