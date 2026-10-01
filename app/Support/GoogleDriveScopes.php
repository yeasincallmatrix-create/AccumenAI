<?php

namespace App\Support;

use Google\Service\Drive as GoogleDrive;
use Google\Service\Oauth2;

/**
 * Canonical Google OAuth scope list for the Drive integration.
 *
 * EVERY caller — connect() (consent), callback() (code exchange) and
 * GoogleDriveService (refresh) — must declare this identical list.
 * A mismatch is what caused the userinfo 401: consent was granted for
 * drive.file only while oauth2->userinfo->get() needs userinfo.email.
 *
 * All three scopes are non-sensitive → no Google verification, no
 * 7-day refresh-token expiry.
 */
final class GoogleDriveScopes
{
    public const ALL = [
        GoogleDrive::DRIVE_FILE,
        Oauth2::USERINFO_EMAIL,
        Oauth2::USERINFO_PROFILE,
    ];

    private function __construct() {}
}
