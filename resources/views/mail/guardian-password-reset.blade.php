<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:24px;background:#f5f7fa">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e8ee">
        <div style="padding:20px 28px;background:#0d6efd;color:#ffffff">
            <strong>AccumenAI — {{ mawa_e('guardian.portal_name') }}</strong>
        </div>
        <div style="padding:28px">
            <h2 style="margin:0 0 12px;font-size:18px;color:#1a2333">{{ mawa_e('guardian.mail_reset_heading') }}</h2>
            <p style="margin:0 0 20px;color:#4a5568;line-height:1.6">{{ mawa_e('guardian.mail_reset_body') }}</p>
            <a href="{{ $resetUrl }}"
               style="display:inline-block;padding:12px 28px;background:#0d6efd;color:#ffffff;border-radius:999px;text-decoration:none;font-weight:600">
                {{ mawa_e('guardian.mail_reset_button') }}
            </a>
            <p style="margin:20px 0 0;color:#718096;font-size:13px;line-height:1.6">{{ mawa_e('guardian.mail_reset_expire') }}</p>
        </div>
    </div>
</body>
</html>