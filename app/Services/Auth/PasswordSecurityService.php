<?php

namespace App\Services\Auth;

/**
 * Alias for PasswordService to satisfy the spec's expected class name.
 * All logic lives in PasswordService; this wrapper ensures both names work.
 */
class PasswordSecurityService extends PasswordService
{
}
