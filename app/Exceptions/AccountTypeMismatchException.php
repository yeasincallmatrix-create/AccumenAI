<?php

namespace App\Exceptions;

use RuntimeException;

class AccountTypeMismatchException extends RuntimeException
{
    public static function staffCannotOwn(): self
    {
        return new self('A staff account cannot hold an institute-owner membership.');
    }

    public static function ownerCannotBeStaff(): self
    {
        return new self('An owner account cannot hold a staff (non-owner) membership.');
    }

    public static function staffCannotConvert(): self
    {
        return new self('A staff account cannot be converted to owner while holding staff memberships.');
    }

    public static function ownerCannotConvert(): self
    {
        return new self('An owner account cannot be converted to staff while holding owner memberships.');
    }
}
