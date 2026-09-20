<?php

namespace App\Enums;

enum BusinessEntityType: string
{
    case SINGLE_ENTITY = 'single_entity';
    case SOLE_PROPRIETORSHIP = 'sole_proprietorship';
    case PARTNERSHIP = 'partnership';
    case PRIVATE_LIMITED = 'private_limited';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE_ENTITY => 'Single Entity',
            self::SOLE_PROPRIETORSHIP => 'Sole Proprietorship',
            self::PARTNERSHIP => 'Partnership',
            self::PRIVATE_LIMITED => 'Private Limited Company',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SINGLE_ENTITY => 'Default mode. Simple accounting for one owner/operator.',
            self::SOLE_PROPRIETORSHIP => 'Single owner with owner capital and drawings tracking.',
            self::PARTNERSHIP => 'Multiple partners with capital accounts and profit-sharing.',
            self::PRIVATE_LIMITED => 'Shareholders, directors, share capital, and dividends.',
        };
    }

    public function settingsRoute(): string
    {
        return match ($this) {
            self::SINGLE_ENTITY => 'settings.advanced-accounting',
            self::SOLE_PROPRIETORSHIP => 'settings.entity.sole-proprietorship',
            self::PARTNERSHIP => 'settings.entity.partnership',
            self::PRIVATE_LIMITED => 'settings.entity.private-limited',
        };
    }

    public function requiresAdvancedMode(): bool
    {
        return $this !== self::SINGLE_ENTITY;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn ($case) => [$case->value => $case->label()]
        )->toArray();
    }

    public static function advancedOptions(): array
    {
        return collect(self::cases())
            ->filter(fn ($case) => $case->requiresAdvancedMode())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
