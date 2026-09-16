<?php

namespace App\Support;

class MedicineDosageForm
{
    public const TABLET      = 'Tablet';
    public const CAPSULE     = 'Capsule';
    public const SYRUP       = 'Syrup';
    public const SUSPENSION  = 'Suspension';
    public const INJECTION   = 'Injection';
    public const DROPS       = 'Drops';
    public const INHALER     = 'Inhaler';
    public const CREAM       = 'Cream';
    public const OINTMENT    = 'Ointment';
    public const GEL         = 'Gel';
    public const SPRAY       = 'Spray';
    public const SUPPOSITORY = 'Suppository';
    public const SACHET      = 'Sachet';
    public const POWDER      = 'Powder';
    public const SOLUTION    = 'Solution';
    public const LOTION      = 'Lotion';
    public const PATCH       = 'Patch';

    public static function all(): array
    {
        return array_keys(config('medicine.dosage_forms'));
    }

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::all(), true);
    }
}
