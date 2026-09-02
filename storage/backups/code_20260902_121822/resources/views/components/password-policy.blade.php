@props(['field' => 'password', 'confirmField' => 'password_confirmation'])
{{-- Accessible live password requirements — frontend UX only, backend is authoritative --}}
<div class="mt-2" data-password-policy data-field="{{ $field }}" data-confirm="{{ $confirmField }}">
    <ul class="list-unstyled small mb-2" aria-live="polite" style="font-size:0.85rem;">
        <li data-req="length"><span class="req-icon">○</span> Minimum 8 characters</li>
        <li data-req="upper"><span class="req-icon">○</span> Uppercase letter (A-Z)</li>
        <li data-req="lower"><span class="req-icon">○</span> Lowercase letter (a-z)</li>
        <li data-req="number"><span class="req-icon">○</span> Number (0-9)</li>
        <li data-req="symbol"><span class="req-icon">○</span> Special character</li>
        <li data-req="match" class="d-none"><span class="req-icon">○</span> Passwords match</li>
    </ul>
    <div class="progress mb-1" style="height:6px;" aria-hidden="true">
        <div class="progress-bar" role="progressbar" style="width:0%" data-strength-bar></div>
    </div>
    <small class="text-muted" data-strength-label>Enter a password</small>
</div>
