<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<p>Hello,</p>
<p>Your backup <strong>{{ $backup->filename }}</strong> has failed.</p>
<p>Error: <strong style="color: #b02a37;">{{ $error }}</strong></p>
<p>Please check your Google Drive connection and free disk space, then try again from Settings → Backup.</p>
<p style="font-size: 13px; color: #666;">If this keeps happening, contact support with the error message above.</p>
<p style="font-size: 12px; color: #999;">Accumen AI — Automated backup notification. No credentials are included in this email.</p>
</body>
</html>
