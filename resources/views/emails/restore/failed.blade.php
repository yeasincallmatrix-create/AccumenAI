<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<p>Hello,</p>
<p>Your restore of backup <strong>#{{ $log->backup_id }}</strong> has failed.</p>
<p>Error: <strong style="color: #b02a37;">{{ $error }}</strong></p>
<p>Your data was not left half-restored — a rollback snapshot was taken before the restore began.</p>
<p style="font-size: 13px; color: #666;">Please retry from Settings → Backup, or contact support with the error message above.</p>
<p style="font-size: 12px; color: #999;">Accumen AI — Automated restore notification. No credentials are included in this email.</p>
</body>
</html>
