<?php

namespace App\Enums;

enum BusinessEntityType: string
{
    case SOLE_PROPRIETORSHIP = 'sole_proprietorship';
    case PARTNERSHIP = 'partnership';
    case PRIVATE_LIMITED = 'private_limited';

    public function label(): string
    {
        return match ($this) {
            self::SOLE_PROPRIETORSHIP => 'Sole Proprietorship',
            self::PARTNERSHIP => 'Partnership',
            self::PRIVATE_LIMITED => 'Private Limited Company',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SOLE_PROPRIETORSHIP => 'Single owner, simple accounting with owner capital and drawings.',
            self::PARTNERSHIP => 'Multiple partners with capital accounts and profit-sharing.',
            self::PRIVATE_LIMITED => 'Shareholders, directors, share capital, and dividends.',
        };
    }

    public function settingsRoute(): string
    {
        return match ($this) {
            self::SOLE_PROPRIETORSHIP => 'settings.entity.sole-proprietorship',
            self::PARTNERSHIP => 'settings.entity.partnership',
            self::PRIVATE_LIMITED => 'settings.entity.private-limited',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn ($case) => [$case->value => $case->label()]
        )->toArray();
    }
}
