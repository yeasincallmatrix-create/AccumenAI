<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<p>Hello,</p>
<p>Your restore has completed successfully.</p>
<p>
    Backup: <strong>#{{ $log->backup_id }}</strong><br>
    Records affected: <strong>{{ is_array($log->records_affected) ? count($log->records_affected) : $log->records_affected }}</strong><br>
    Completed: <strong>{{ optional($log->completed_at)->format('Y-m-d H:i') }}</strong>
</p>
<p>A rollback snapshot was kept until <strong>{{ optional($log->rollback_expires_at)->format('Y-m-d H:i') }}</strong>.</p>
<p style="font-size: 13px; color: #666;">If this restore was not expected, contact support immediately.</p>
<p style="font-size: 12px; color: #999;">Accumen AI — Automated restore notification. No credentials are included in this email.</p>
</body>
</html>
