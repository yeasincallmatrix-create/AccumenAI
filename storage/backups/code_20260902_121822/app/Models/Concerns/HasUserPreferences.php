<?php

namespace App\Models\Concerns;

/**
 * Per-account UI preferences stored in the `preferences` JSON column.
 *
 * Every value read/written through this trait is scoped to the account
 * instance, so changing them never affects any other user.
 */
trait HasUserPreferences
{
    /**
     * All preferences for this account, merged over the defaults.
     */
    public function allPreferences(): array
    {
        $stored = is_array($this->preferences) ? $this->preferences : [];

        return array_replace($this->defaultPreferences(), $stored);
    }

    /**
     * Read a single preference.
     */
    public function preference(string $key, mixed $default = null): mixed
    {
        $all = $this->allPreferences();

        return $all[$key] ?? $default;
    }

    /**
     * Write a single preference and persist it immediately.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $current = is_array($this->preferences) ? $this->preferences : [];
        $current[$key] = $value;

        $this->forceFill(['preferences' => $current])->save();
    }

    /**
     * Defaults merged under stored values.
     */
    protected function defaultPreferences(): array
    {
        return [
            'theme' => 'default',
        ];
    }
}
