<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Crypto;
use App\Core\DB;
use InvalidArgumentException;

final class ProviderManager
{
    public const DRIVERS = [
        'hostinger' => HostingerDriver::class,
        'manual' => ManualDriver::class,
    ];

    public static function driverLabel(string $driver): string
    {
        $class = self::DRIVERS[$driver] ?? null;
        return $class ? $class::label() : ucfirst($driver);
    }

    public static function make(array $provider): ProviderDriver
    {
        $class = self::DRIVERS[$provider['driver']] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException('Unknown provider driver: ' . $provider['driver']);
        }
        return new $class(self::credentials($provider));
    }

    /** Load an enabled provider's driver by id, or throw a ProviderException. */
    public static function forId(?int $providerId): ProviderDriver
    {
        $p = $providerId ? DB::one('SELECT * FROM providers WHERE id = ?', [$providerId]) : null;
        if (!$p) {
            throw new ProviderException('This resource is not linked to a provider account.');
        }
        if (!$p['is_enabled']) {
            throw new ProviderException("Provider account \"{$p['label']}\" is disabled.");
        }
        return self::make($p);
    }

    public static function credentials(array $provider): array
    {
        $json = Crypto::decrypt($provider['credentials'] ?? null);
        $data = $json ? json_decode($json, true) : [];
        return is_array($data) ? $data : [];
    }

    public static function encryptCredentials(array $credentials): string
    {
        return Crypto::encrypt(json_encode($credentials, JSON_UNESCAPED_SLASHES));
    }
}
