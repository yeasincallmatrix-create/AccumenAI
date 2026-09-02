<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<p>Hello,</p>
<p>Your Accumen AI verification code is <strong style="font-size: 22px; letter-spacing: 2px;">{{ $code }}</strong>.</p>
<p>This code expires in {{ \App\Support\IdentityConfig::emailOtp('expires_minutes', 15) }} minutes. Do not share this code with anyone.</p>
<p style="font-size: 13px; color: #666;">If you did not request this code, please ignore this email or contact support. This code was sent to {{ $maskedEmail ?? 'your email' }}.</p>
<p style="font-size: 12px; color: #999;">Accumen AI — Secure verification. No password, TOTP secret, or API key is included in this email.</p>
</body>
</html>
