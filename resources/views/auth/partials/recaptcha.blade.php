@if (\App\Services\Auth\RecaptchaService::enabled())
    @if (\App\Services\Auth\RecaptchaService::isV3())
        <input type="hidden" name="g-recaptcha-response" value="">
    @else
        <div class="mb-4 d-flex justify-content-center">
            <div class="g-recaptcha" data-sitekey="{{ \App\Services\Auth\RecaptchaService::siteKey() }}" data-theme="light"></div>
        </div>
    @endif
@endif
