<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Support\IndianStates;
use App\Support\Permissions;

final class CustomerService
{
    /** Validate billing profile input. Returns [data, errors]. */
    public static function validateProfile(array $in): array
    {
        $errors = [];
        $data = [
            'name' => trim((string) ($in['name'] ?? '')),
            'company' => trim((string) ($in['company'] ?? '')) ?: null,
            'email' => strtolower(trim((string) ($in['email'] ?? ''))),
            'phone' => trim((string) ($in['phone'] ?? '')) ?: null,
            'gstin' => strtoupper(trim((string) ($in['gstin'] ?? ''))) ?: null,
            'address_line1' => trim((string) ($in['address_line1'] ?? '')) ?: null,
            'address_line2' => trim((string) ($in['address_line2'] ?? '')) ?: null,
            'city' => trim((string) ($in['city'] ?? '')) ?: null,
            'state_code' => trim((string) ($in['state_code'] ?? '')) ?: null,
            'country' => strtoupper(trim((string) ($in['country'] ?? 'IN'))) ?: 'IN',
            'postal_code' => trim((string) ($in['postal_code'] ?? '')) ?: null,
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 150) {
            $errors[] = 'Customer name is required (max 150 characters).';
        }
        if (!valid_email($data['email'])) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($data['phone'] !== null && !preg_match('/^[0-9+\-\s()]{6,30}$/', $data['phone'])) {
            $errors[] = 'Enter a valid phone number.';
        }
        if (!preg_match('/^[A-Z]{2}$/', $data['country'])) {
            $errors[] = 'Country must be a 2-letter code, e.g. IN.';
        }
        if ($data['country'] === 'IN' && $data['state_code'] !== null && !isset(IndianStates::ALL[$data['state_code']])) {
            $errors[] = 'Choose a valid state.';
        }
        if ($data['country'] !== 'IN') {
            $data['state_code'] = null;
        }
        if ($data['gstin'] !== null) {
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $data['gstin'])) {
                $errors[] = 'GSTIN format is invalid (e.g. 07ABCDE1234F1Z5).';
            } elseif ($data['state_code'] !== null && substr($data['gstin'], 0, 2) !== $data['state_code']) {
                $errors[] = 'GSTIN state code does not match the selected state.';
            }
        }
        foreach (['company' => 150, 'address_line1' => 255, 'address_line2' => 255, 'city' => 100, 'postal_code' => 20] as $k => $max) {
            if ($data[$k] !== null && mb_strlen($data[$k]) > $max) {
                $errors[] = ucfirst(str_replace('_', ' ', $k)) . " is too long (max $max).";
            }
        }
        return [$data, $errors];
    }

    public static function create(array $data, array $owner, ?int $createdBy): int
    {
        return DB::transaction(static function () use ($data, $owner, $createdBy): int {
            $id = DB::insert('customers', [...$data, 'status' => 'active', 'created_by' => $createdBy, 'created_at' => now(), 'updated_at' => now()]);
            $code = Settings::get('customer.code_prefix') . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::update('customers', ['code' => $code], 'id = ?', [$id]);
            self::createUser($id, [
                'name' => $owner['name'] ?: $data['name'],
                'email' => $owner['email'],
                'phone' => $data['phone'],
                'password' => $owner['password'],
                'is_owner' => true,
                'permissions' => [],
                'status' => 'active',
            ]);
            Logger::activity('customers', 'create', "Created customer {$data['name']} ($code)", 'customer', $id, $id);
            return $id;
        });
    }

    public static function createUser(int $customerId, array $u): int
    {
        return DB::insert('users', [
            'type' => 'customer',
            'customer_id' => $customerId,
            'name' => $u['name'],
            'email' => strtolower($u['email']),
            'phone' => $u['phone'] ?? null,
            'password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
            'is_owner' => !empty($u['is_owner']) ? 1 : 0,
            'permissions' => json_encode(self::cleanPermissions($u['permissions'] ?? [])),
            'status' => $u['status'] ?? 'active',
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function cleanPermissions(array $perms): array
    {
        return array_values(array_intersect(array_keys(Permissions::customer()), array_map('strval', $perms)));
    }

    public static function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        return (bool) DB::value('SELECT id FROM users WHERE email = ? AND id <> ?', [strtolower($email), $exceptUserId ?? 0]);
    }
}
