<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Read/write access to the global settings store. Kept intentionally
 * small: string and boolean values under stable keys. Unknown keys
 * simply fall back to their default rather than erroring.
 */
class Settings
{
    public static function get(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : $value === '1';
    }

    public static function setBoolean(string $key, bool $value): void
    {
        static::set($key, $value ? '1' : '0');
    }
}
