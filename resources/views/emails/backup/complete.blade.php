<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<p>Hello,</p>
<p>Your backup has completed successfully.</p>
<p>
    File: <strong>{{ $backup->filename }}</strong><br>
    Size: <strong>{{ $sizeMb }} MB</strong><br>
    Completed: <strong>{{ optional($backup->completed_at)->format('Y-m-d H:i') }}</strong>
</p>
<p>The backup is encrypted with AES-256 and stored on your connected Google Drive.</p>
<p style="font-size: 13px; color: #666;">You can manage backups from Settings → Backup.</p>
<p style="font-size: 12px; color: #999;">Accumen AI — Automated backup notification. No credentials are included in this email.</p>
</body>
</html>
