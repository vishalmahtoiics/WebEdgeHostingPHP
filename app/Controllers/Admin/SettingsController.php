<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Settings;
use App\Services\Mailer;
use App\Support\IndianStates;
use App\Support\Money;
use App\Support\SettingsSchema;

final class SettingsController extends Controller
{
    private const IMAGE_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    public function index(): string
    {
        $tabs = SettingsSchema::tabs();
        $active = array_key_exists(query('tab'), $tabs) ? query('tab') : 'general';
        return $this->view('admin/settings/index', ['title' => 'Settings', 'tabs' => $tabs, 'active' => $active]);
    }

    public function update(): string
    {
        $tabs = SettingsSchema::tabs();
        $tab = input_str('tab');
        if (!isset($tabs[$tab])) {
            abort(400);
        }
        $errors = [];
        $values = [];
        foreach ($tabs[$tab]['fields'] as $key => $field) {
            $name = str_replace('.', '__', $key);
            $raw = $_POST[$name] ?? null;
            $raw = is_string($raw) ? trim($raw) : null;
            $label = $field['label'];

            switch ($field['type']) {
                case 'bool':
                    $values[$key] = $raw ? '1' : '0';
                    continue 2;
                case 'secret':
                    if ($raw !== null && $raw !== '') {
                        $values[$key] = $raw;
                    } elseif (!empty($_POST[$name . '__clear'])) {
                        $values[$key] = '';
                    }
                    continue 2;
                case 'image':
                    $upload = $this->handleImage($name, $key, $errors);
                    if ($upload !== null) {
                        $values[$key] = $upload;
                    } elseif (!empty($_POST[$name . '__clear'])) {
                        $values[$key] = '';
                    }
                    continue 2;
            }

            $raw ??= '';
            if (!empty($field['required']) && $raw === '') {
                $errors[] = "$label is required.";
                continue;
            }
            if ($raw !== '') {
                $ok = match ($field['type']) {
                    'email' => valid_email($raw),
                    'url' => (bool) filter_var($raw, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $raw),
                    'number' => ctype_digit($raw) && strlen($raw) <= 9,
                    'decimal' => (bool) preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw) && Money::percentToBps($raw) <= 10000,
                    'color' => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $raw),
                    'state' => isset(IndianStates::ALL[$raw]),
                    'select' => array_key_exists($raw, $field['options']),
                    default => mb_strlen($raw) <= 5000,
                };
                if ($ok && isset($field['pattern'])) {
                    $ok = (bool) preg_match($field['pattern'], $raw);
                }
                if (!$ok) {
                    $errors[] = "$label: invalid value." . (isset($field['help']) ? ' ' . $field['help'] : '');
                    continue;
                }
            }
            $values[$key] = $raw;
        }
        if ($errors) {
            $this->failed('/admin/settings?tab=' . $tab, $errors);
        }

        $changed = [];
        foreach ($values as $key => $value) {
            $isSecret = SettingsSchema::field($key)['type'] === 'secret';
            if ($isSecret || (string) Settings::get($key) !== $value) {
                Settings::set($key, $value);
                $changed[] = $key;
            }
        }
        if ($changed) {
            // Never log values: keys only.
            Logger::activity('settings', 'update', 'Updated settings: ' . implode(', ', $changed));
            $secretKeys = array_filter($changed, static fn ($k) => SettingsSchema::field($k)['type'] === 'secret');
            if ($secretKeys || $tab === 'security') {
                Logger::security('settings_change', 'info', 'Changed ' . ($secretKeys ? 'credentials: ' . implode(', ', $secretKeys) : 'security settings: ' . implode(', ', $changed)));
            }
        }
        $this->success('/admin/settings?tab=' . $tab, $changed ? 'Settings saved.' : 'No changes.');
    }

    public function testEmail(): string
    {
        $to = (string) Auth::user()['email'];
        $ok = Mailer::send($to, 'Test email from ' . brand_name(), '<p>Your email settings are working.</p>');
        $ok ? $this->success('/admin/settings?tab=email', "Test email sent to $to.")
            : $this->failed('/admin/settings?tab=email', ['Could not send the test email. Check the settings and storage/logs/php-error.log.']);
    }

    private function handleImage(string $name, string $key, array &$errors): ?string
    {
        $file = $_FILES[$name] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Upload failed. Please try again.';
            return null;
        }
        if ($file['size'] > 1048576) {
            $errors[] = 'Images must be 1 MB or smaller.';
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = self::IMAGE_TYPES[$mime] ?? null;
        if ($ext === null || ($ext !== 'ico' && @getimagesize($file['tmp_name']) === false)) {
            $errors[] = 'Upload a PNG, JPG, WebP or ICO image. SVG is not allowed.';
            return null;
        }
        $dir = BASE_PATH . '/public/uploads/branding';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $errors[] = 'Upload folder is not writable.';
            return null;
        }
        $filename = str_replace('.', '-', $key) . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
            $errors[] = 'Could not save the uploaded file.';
            return null;
        }
        @chmod("$dir/$filename", 0644);
        $old = (string) Settings::get($key);
        if ($old !== '' && str_starts_with($old, 'uploads/branding/') && is_file(BASE_PATH . '/public/' . $old)) {
            @unlink(BASE_PATH . '/public/' . $old);
        }
        return 'uploads/branding/' . $filename;
    }
}
