<?php
declare(strict_types=1);

namespace App\Core;

use App\Support\SettingsSchema;
use PDOException;

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (array_key_exists($key, self::$cache)) {
            $field = SettingsSchema::field($key);
            $value = self::$cache[$key];
            if ($field && $field['type'] === 'secret') {
                return Crypto::decrypt($value);
            }
            return $value;
        }
        if ($default !== null) {
            return $default;
        }
        return SettingsSchema::field($key)['default'] ?? null;
    }

    public static function bool(string $key): bool
    {
        return in_array((string) self::get($key), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function set(string $key, ?string $value): void
    {
        $field = SettingsSchema::field($key);
        if ($field && $field['type'] === 'secret' && $value !== null && $value !== '') {
            $value = Crypto::encrypt($value);
        }
        DB::run(
            'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, $value]
        );
        self::load();
        self::$cache[$key] = $value;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            foreach (DB::all('SELECT `key`, `value` FROM settings') as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (PDOException) {
            // Not installed yet; fall back to defaults.
        }
    }
}
