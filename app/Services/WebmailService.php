<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use PDO;
use RuntimeException;

/**
 * Installs and configures the customer webmail (Roundcube) on a subdomain of
 * the same hosting account as the panel.
 *
 * Layout (nothing sensitive is web-reachable):
 *   storage/webmail/app/    Roundcube itself (storage/ is never served)
 *   storage/webmail/data/   SQLite database, temp files and logs
 *   <web folder>/           two small entry files, .htaccess and the logo
 */
final class WebmailService
{
    public const VERSION = '1.7.4';
    public const DOWNLOAD_URL = 'https://github.com/roundcube/roundcubemail/releases/download/1.7.4/roundcubemail-1.7.4-complete.tar.gz';
    /** SHA-256 of the release file above; the download is rejected if it differs. */
    public const SHA256 = '2c6c878f0093f1bf7fb6086781d2dd9269d652c016b86939c157c5f1729139a2';

    public const DEFAULT_IMAP = 'ssl://imap.hostinger.com:993';
    public const DEFAULT_SMTP = 'ssl://smtp.hostinger.com:465';

    /** Marker written into the entry files, so we only ever overwrite our own files. */
    private const MARKER = 'WebEdge webmail entry file';

    /** Files a new (sub)domain folder may already contain; they are moved aside on install. */
    private const PLACEHOLDER_FILES = ['default.php', 'index.html', 'index.htm', 'default.html'];

    public static function baseDir(): string
    {
        return BASE_PATH . '/storage/webmail';
    }

    public static function appDir(): string
    {
        return self::baseDir() . '/app';
    }

    public static function dataDir(): string
    {
        return self::baseDir() . '/data';
    }

    public static function status(): array
    {
        $installed = (string) Settings::get('webmail.installed_version', '');
        $docroot = (string) Settings::get('webmail.docroot', '');
        return [
            'installed' => $installed !== '' && is_file(self::appDir() . '/program/include/iniset.php'),
            'version' => $installed,
            'docroot' => $docroot,
            'url' => (string) Settings::get('mail.webmail_url', ''),
            'imap_host' => (string) Settings::get('webmail.imap_host', self::DEFAULT_IMAP),
            'smtp_host' => (string) Settings::get('webmail.smtp_host', self::DEFAULT_SMTP),
            'filters' => Settings::bool('webmail.filters'),
            'installed_at' => (string) Settings::get('webmail.installed_at', ''),
            'extra_url' => (string) Settings::get('webmail.extra_url', ''),
            'extra_docroot' => (string) Settings::get('webmail.extra_docroot', ''),
            'entry_ok' => $docroot !== '' && is_file($docroot . '/index.php') && str_contains((string) @file_get_contents($docroot . '/index.php'), self::MARKER),
        ];
    }

    /** Likely web folders for a mail subdomain on this hosting account (Hostinger layouts first). */
    public static function suggestDocroots(string $host): array
    {
        $out = [];
        $panel = realpath(BASE_PATH) ?: BASE_PATH;
        if (preg_match('#^(/home/[^/]+)/domains/([^/]+)/#', $panel . '/', $m)) {
            [$home, $mainDomain] = [$m[1], $m[2]];
            if ($host !== '') {
                $out[] = "$home/domains/$host/public_html";
                $label = str_ends_with($host, '.' . $mainDomain) ? substr($host, 0, -strlen('.' . $mainDomain)) : '';
                if ($label !== '') {
                    $out[] = "$home/domains/$mainDomain/public_html/$label";
                }
            }
        }
        // Existing folders first.
        usort($out, static fn ($a, $b) => (int) is_dir($b) <=> (int) is_dir($a));
        return array_values(array_unique($out));
    }

    /**
     * Validate the web folder: it must exist, must not be (or contain, or be
     * inside) the panel, and must hold nothing but placeholder or our own files.
     */
    public static function checkDocroot(string $docroot, bool $allowPanelPublicChild = false): string
    {
        $real = realpath($docroot);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException("The folder $docroot does not exist. Create the subdomain in your hosting panel first, then use the folder it shows.");
        }
        $panel = realpath(BASE_PATH) ?: BASE_PATH;
        $panelPublicChild = $allowPanelPublicChild && dirname($real) === $panel . '/public';
        if (!$panelPublicChild && ($real === $panel || str_starts_with($real . '/', $panel . '/') || str_starts_with($panel . '/', $real . '/'))) {
            throw new RuntimeException('The webmail folder cannot be the control panel folder, inside it, or a folder that contains it. Use the subdomain\'s own folder.');
        }
        if (substr_count(trim($real, '/'), '/') < 2) {
            throw new RuntimeException('Choose the subdomain\'s own folder, not a system folder.');
        }
        if (!is_writable($real)) {
            throw new RuntimeException("The folder $real is not writable by PHP.");
        }
        $foreign = [];
        foreach (scandir($real) ?: [] as $f) {
            if ($f === '.' || $f === '..' || $f === '.well-known' || $f === 'cgi-bin' || in_array(strtolower($f), self::PLACEHOLDER_FILES, true) || str_ends_with($f, '.webedge-bak')) {
                continue;
            }
            if (in_array($f, ['index.php', 'static.php', '.htaccess', 'favicon.ico', 'blank.html', 'brand-mark.svg'], true) || str_starts_with($f, 'brand-logo')) {
                if (in_array($f, ['index.php', 'static.php'], true) && !str_contains((string) @file_get_contents("$real/$f"), self::MARKER)) {
                    $foreign[] = $f;
                }
                continue;
            }
            $foreign[] = $f;
        }
        if ($foreign) {
            throw new RuntimeException('The folder ' . $real . ' already contains other files (' . implode(', ', array_slice($foreign, 0, 8)) . '). Use an empty folder for webmail, or remove those files first.');
        }
        return $real;
    }

    /**
     * Install (or repair/reconfigure) webmail. Downloads Roundcube only when the
     * pinned version is not already present. Returns a short summary.
     */
    public static function install(string $url, string $docroot, string $imapHost, string $smtpHost, bool $filters, string $extraUrl = '', string $extraDocroot = ''): string
    {
        self::requirements();
        $url = rtrim($url, '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://[a-z0-9.\-]+(:\d+)?$#i', $url)) {
            throw new RuntimeException('Enter the webmail address, e.g. https://mails.yourdomain.com (no path).');
        }
        foreach (['IMAP' => $imapHost, 'SMTP' => $smtpHost] as $label => $h) {
            if (!preg_match('#^((ssl|tls)://)?[a-z0-9.\-]+(:\d{1,5})?$#i', $h)) {
                throw new RuntimeException("Enter the $label server like ssl://imap.example.com:993.");
            }
        }
        $docroot = self::checkDocroot($docroot);
        $extraUrl = rtrim(trim($extraUrl), '/');
        $extra = null;
        if ($extraUrl !== '') {
            if (!filter_var($extraUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://[a-z0-9.\-]+(:\d+)?/[a-z0-9_\-]{1,40}$#i', $extraUrl)) {
                throw new RuntimeException('Enter the second address like https://yourdomain.com/mails (one folder name, no trailing parts).');
            }
            $extra = self::prepareExtraDocroot($extraDocroot, (string) basename((string) parse_url($extraUrl, PHP_URL_PATH)));
            if ($extra === $docroot) {
                throw new RuntimeException('The second address needs its own folder.');
            }
        }

        @set_time_limit(600);
        foreach ([self::baseDir(), self::dataDir(), self::dataDir() . '/temp', self::dataDir() . '/logs'] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0750, true)) {
                throw new RuntimeException("Could not create $d.");
            }
        }
        $downloaded = false;
        if (!self::appIsVersion(self::VERSION)) {
            self::downloadAndExtract();
            $downloaded = true;
        }
        self::writeBrandingPlugin();
        self::writeConfig($url, $imapHost, $smtpHost, $filters);
        self::initDatabase();
        self::writeEntryFiles($docroot, $url);
        $previousExtra = (string) Settings::get('webmail.extra_docroot', '');
        if ($previousExtra !== '' && $previousExtra !== $extra) {
            self::removeEntryFiles($previousExtra);
        }
        if ($extra !== null) {
            self::writeEntryFiles($extra, $url, false);
        }

        Settings::set('webmail.installed_version', self::VERSION);
        Settings::set('webmail.docroot', $docroot);
        Settings::set('webmail.extra_url', $extra !== null ? $extraUrl : '');
        Settings::set('webmail.extra_docroot', $extra ?? '');
        Settings::set('webmail.imap_host', $imapHost);
        Settings::set('webmail.smtp_host', $smtpHost);
        Settings::set('webmail.filters', $filters ? '1' : '0');
        Settings::set('webmail.installed_at', now());
        Settings::set('mail.webmail_url', $url);
        Logger::activity('settings', 'webmail_install', ($downloaded ? 'Installed' : 'Reconfigured') . " webmail $url" . ($extra !== null ? " and $extraUrl" : '') . ' (Roundcube ' . self::VERSION . ')');
        return $downloaded ? 'Webmail installed' : 'Webmail settings updated';
    }

    /**
     * Folder for the second (path) address, e.g. webedgesolution.in/mails.
     * When that domain serves this panel, the folder goes inside the panel's
     * public/ folder (the panel's .htaccess sends every request there);
     * otherwise it is created inside the other site's web folder.
     */
    private static function prepareExtraDocroot(string $folder, string $name): string
    {
        if (!preg_match('/^[a-z0-9_\-]{1,40}$/i', $name) || in_array(strtolower($name), ['assets', 'uploads', 'public', 'admin', 'customer', 'login', 'webhooks', 'webmail-api', 'install.php', 'index.php'], true)) {
            throw new RuntimeException("\"$name\" cannot be used as the webmail folder name.");
        }
        $folder = rtrim(trim($folder), '/');
        if ($folder === '') {
            throw new RuntimeException('Enter the web folder for the second address (the main site\'s folder followed by /' . $name . ').');
        }
        $parent = realpath(dirname($folder));
        if ($parent === false) {
            throw new RuntimeException('The folder ' . dirname($folder) . ' does not exist. Enter the main site\'s web folder followed by /' . $name . '.');
        }
        $panel = realpath(BASE_PATH) ?: BASE_PATH;
        $inPanel = $parent === $panel || $parent === $panel . '/public';
        $target = ($inPanel ? $panel . '/public' : $parent) . '/' . $name;
        if (!$inPanel && basename($folder) !== $name) {
            throw new RuntimeException("The folder must end in /$name to match the address.");
        }
        if (!is_dir($target) && !@mkdir($target, 0755)) {
            throw new RuntimeException("Could not create the folder $target.");
        }
        return self::checkDocroot($target, $inPanel);
    }

    /** Remove our entry files from a folder we no longer use (never anything else). */
    private static function removeEntryFiles(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (['index.php', 'static.php', '.htaccess', 'blank.html'] as $f) {
            if (is_file("$dir/$f") && ($f === 'blank.html' || str_contains((string) file_get_contents("$dir/$f"), self::MARKER))) {
                @unlink("$dir/$f");
            }
        }
        @rmdir($dir);
    }

    public static function requirements(): void
    {
        $missing = [];
        foreach (['pdo_sqlite' => 'pdo_sqlite', 'intl' => 'intl', 'mbstring' => 'mbstring', 'openssl' => 'openssl', 'dom' => 'dom', 'fileinfo' => 'fileinfo', 'phar' => 'Phar', 'zlib' => 'zlib'] as $label => $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            throw new RuntimeException('Webmail needs these PHP extensions: ' . implode(', ', $missing) . '. Enable them in your hosting PHP settings (Hostinger: Advanced → PHP Configuration → PHP extensions).');
        }
    }

    private static function appIsVersion(string $version): bool
    {
        $f = self::appDir() . '/program/include/iniset.php';
        return is_file($f) && str_contains((string) file_get_contents($f), "'RCMAIL_VERSION', '$version'");
    }

    private static function downloadAndExtract(): void
    {
        $tmp = self::baseDir() . '/download-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0750, true);
        $archive = "$tmp/roundcube.tar.gz";
        try {
            $body = self::fetch(self::DOWNLOAD_URL);
            if (!hash_equals(self::SHA256, hash('sha256', $body))) {
                throw new RuntimeException('The downloaded webmail package failed its integrity check and was discarded. Please try again later.');
            }
            file_put_contents($archive, $body);
            unset($body);
            $phar = new \PharData($archive);
            $phar->extractTo($tmp, null, true);
            $src = "$tmp/roundcubemail-" . self::VERSION;
            if (!is_file("$src/program/include/iniset.php")) {
                throw new RuntimeException('The webmail package did not have the expected contents.');
            }
            // Never ship the web installer.
            self::rrmdir("$src/installer");
            @unlink("$src/public_html/installer.php");
            $old = self::appDir() . '.old';
            self::rrmdir($old);
            if (is_dir(self::appDir()) && !@rename(self::appDir(), $old)) {
                throw new RuntimeException('Could not replace the previous webmail files.');
            }
            if (!@rename($src, self::appDir())) {
                throw new RuntimeException('Could not move the webmail files into place.');
            }
            self::rrmdir($old);
        } finally {
            self::rrmdir($tmp);
        }
    }

    private static function fetch(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $code !== 200) {
            throw new RuntimeException('Could not download the webmail package (' . ($err ?: "HTTP $code") . '). Check that the server can reach github.com.');
        }
        return $body;
    }

    /** Shared secret the webmail uses when asking the panel to change a mailbox password. */
    public static function apiSecret(): string
    {
        return self::secret('webmail.api_secret', static fn (): string => bin2hex(random_bytes(32)));
    }

    /** A generated secret kept encrypted in settings. */
    private static function secret(string $key, callable $make): string
    {
        $stored = (string) Settings::get($key, '');
        $value = $stored !== '' ? (string) Crypto::decrypt($stored) : '';
        if ($value === '') {
            $value = $make();
            Settings::set($key, Crypto::encrypt($value));
        }
        return $value;
    }

    private static function writeConfig(string $url, string $imapHost, string $smtpHost, bool $filters): void
    {
        $desKey = self::secret('webmail.des_key', static fn (): string => substr(str_replace(['+', '/', '='], ['A', 'B', 'C'], base64_encode(random_bytes(24))), 0, 24));
        $plugins = ['archive', 'zipdownload', 'markasjunk', 'newmail_notifier', 'emoticons', 'attachment_reminder', 'hide_blockquote', 'identity_select', 'password', 'webedge_branding'];
        if ($filters) {
            $plugins[] = 'managesieve';
        }
        $sieveHost = preg_replace('#^(ssl|tls)://#', '', (string) preg_replace('#:\d+$#', '', $imapHost));
        $https = str_starts_with($url, 'https://');
        $config = [
            'db_dsnw' => 'sqlite:///' . self::dataDir() . '/roundcube.db?mode=0640',
            'imap_host' => $imapHost,
            'smtp_host' => $smtpHost,
            'smtp_user' => '%u',
            'smtp_pass' => '%p',
            'des_key' => $desKey,
            'product_name' => brand_name() . ' Mail',
            'support_url' => (string) (Settings::get('brand.support_url') ?: ''),
            'skin' => 'elastic',
            'skin_logo' => self::skinLogo($url),
            'blankpage_url' => $url . '/blank.html', // replaced per request below
            'webedge_favicon' => $url . self::faviconFile(),
            'plugins' => $plugins,
            'temp_dir' => self::dataDir() . '/temp/',
            'log_dir' => self::dataDir() . '/logs/',
            'log_driver' => 'file',
            'enable_installer' => false,
            'use_https' => $https,
            'force_https' => $https,
            // Mobile and many home connections change IP address often; binding
            // sessions to one IP would sign people out. Logins stay rate-limited.
            'ip_check' => false,
            'login_rate_limit' => 5,
            'session_lifetime' => 30,
            'session_samesite' => 'Lax',
            'x_frame_options' => 'sameorigin',
            'useragent' => 'Webmail',
            'mime_param_folding' => 0,
            'draft_autosave' => 60,
            'language' => 'en_US',
            'enable_spellcheck' => false,
            'identities_level' => 0,
            'managesieve_host' => 'tls://' . $sieveHost . ':4190',
            'managesieve_vacation' => 1,
            'password_driver' => 'httpapi',
            'password_confirm_current' => true,
            'password_minimum_length' => 10,
            'password_httpapi_url' => rtrim((string) config('app.url'), '/') . '/webmail-api/password',
            'password_httpapi_method' => 'POST',
            'password_httpapi_var_user' => 'user',
            'password_httpapi_var_curpass' => 'curpass',
            'password_httpapi_var_newpass' => 'newpass',
            'password_httpapi_expect' => '/^ok$/i',
            'password_http_client' => ['timeout' => 30, 'headers' => ['X-WebEdge-Webmail' => self::apiSecret()]],
            'webedge_primary_color' => (string) (Settings::get('brand.primary_color') ?: '#2563eb'),
        ];
        $php = "<?php\n// Generated by the WebEdge control panel (Settings → Webmail). Changes here are overwritten.\n\$config = " . var_export($config, true) . ";\n"
            // Webmail can be opened at several addresses (mails.example.com/ and example.com/mails/):
            // point the empty reading pane at the blank page of the address in use.
            // (A full URL: Roundcube resolves bare paths inside its skin folder.)
            . "\$__h = (string) (\$_SERVER['HTTP_HOST'] ?? '');\n"
            . "if (preg_match('/^[a-z0-9.\\-]+(:\\d{1,5})?\$/i', \$__h)) {\n"
            . "    \$__s = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') || (\$_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';\n"
            . "    \$config['blankpage_url'] = \$__s . '://' . \$__h . preg_replace('#[^/]*\$#', '', (string) parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) . 'blank.html';\n"
            . "}\n";
        $file = self::appDir() . '/config/config.inc.php';
        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the webmail configuration.');
        }
        @chmod($file, 0640);
    }

    private static function initDatabase(): void
    {
        $db = self::dataDir() . '/roundcube.db';
        $pdo = new PDO('sqlite:' . $db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $has = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
        if (!$has) {
            $pdo->exec((string) file_get_contents(self::appDir() . '/SQL/sqlite.initial.sql'));
        }
        @chmod($db, 0640);
    }

    /** The uploaded brand logo copied into the web folder (file name), or null when there is none. */
    private static function logoFile(): ?string
    {
        $logo = (string) Settings::get('brand.logo', '');
        if ($logo === '' || !is_file(BASE_PATH . '/public/' . ltrim($logo, '/'))) {
            return null;
        }
        return 'brand-logo.' . strtolower(pathinfo($logo, PATHINFO_EXTENSION));
    }

    /** Logos for every Elastic screen: the uploaded logo, or generated brand logos. Never Roundcube's. */
    private static function skinLogo(string $url): array
    {
        // Full URLs: Roundcube resolves bare paths inside its skin folder.
        if ($logo = self::logoFile()) {
            return ['elastic:*' => "$url/$logo"];
        }
        return [
            'elastic:login' => "$url/brand-logo.svg",
            'elastic:login[dark]' => "$url/brand-logo-dark.svg",
            'elastic:*' => "$url/brand-mark.svg",
            '[print]' => "$url/brand-logo.svg",
        ];
    }

    private static function faviconFile(): string
    {
        $favicon = (string) Settings::get('brand.favicon', '');
        return $favicon !== '' && is_file(BASE_PATH . '/public/' . ltrim($favicon, '/')) ? '/favicon.ico' : '/brand-mark.svg';
    }

    /** Write generated brand images and the blank reading-pane page into the web folder. */
    private static function writeBrandAssets(string $docroot): void
    {
        $color = (string) (Settings::get('brand.primary_color') ?: '#2563eb');
        $color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#2563eb';
        $name = brand_name() . ' Mail';
        $initial = mb_strtoupper(mb_substr(brand_name(), 0, 1)) ?: 'M';
        $x = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $mark = static fn (int $size, int $x0 = 0, int $y0 = 0): string => '<rect x="' . $x0 . '" y="' . $y0 . '" width="' . $size . '" height="' . $size . '" rx="' . (int) round($size * 0.22) . '" fill="' . $color . '"/>'
            . '<text x="' . ($x0 + $size / 2) . '" y="' . ($y0 + $size * 0.68) . '" font-family="Segoe UI,Roboto,Helvetica,Arial,sans-serif" font-size="' . (int) round($size * 0.55) . '" font-weight="700" fill="#fff" text-anchor="middle">' . $x($initial) . '</text>';
        $width = 92 + (int) ceil(mb_strlen($name) * 21);
        $wide = static fn (string $textColor): string => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="72" viewBox="0 0 ' . $width . ' 72">' . $mark(64, 0, 4)
            . '<text x="80" y="48" font-family="Segoe UI,Roboto,Helvetica,Arial,sans-serif" font-size="34" font-weight="600" fill="' . $textColor . '">' . $x($name) . '</text></svg>';
        file_put_contents("$docroot/brand-mark.svg", '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">' . $mark(64) . '</svg>');
        file_put_contents("$docroot/brand-logo.svg", $wide('#1f2937'));
        file_put_contents("$docroot/brand-logo-dark.svg", $wide('#f3f4f6'));
    }

    /** The empty reading-pane page (a faint brand logo). */
    private static function writeBlankPage(string $docroot, string $url): void
    {
        $logo = self::logoFile() ?? 'brand-logo.svg';
        file_put_contents("$docroot/blank.html", '<!DOCTYPE html><html><head><meta charset="UTF-8"><title></title><style>html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;background:#fff}'
            . 'img{width:28%;max-width:220px;opacity:.12;filter:grayscale(1)}html.dark-mode body{background:#21292c}</style></head><body><img src="' . htmlspecialchars("$url/$logo", ENT_QUOTES) . '" alt=""></body></html>');
    }

    private static function writeEntryFiles(string $docroot, string $url, bool $primary = true): void
    {
        $app = self::appDir() . '/public_html';
        foreach (['index.php', 'static.php'] as $f) {
            $code = "<?php\n// " . self::MARKER . " — generated by the WebEdge control panel. Do not edit.\nrequire " . var_export("$app/$f", true) . ";\n";
            if (file_put_contents("$docroot/$f", $code, LOCK_EX) === false) {
                throw new RuntimeException("Could not write $docroot/$f.");
            }
        }
        foreach (self::PLACEHOLDER_FILES as $f) {
            foreach (scandir($docroot) ?: [] as $existing) {
                if (strtolower($existing) === $f) {
                    @rename("$docroot/$existing", "$docroot/$existing.webedge-bak");
                }
            }
        }
        $hasFavicon = false;
        if ($primary) {
            foreach (glob("$docroot/brand-logo.*") ?: [] as $old) {
                @unlink($old);
            }
            self::writeBrandAssets($docroot);
            if ($logo = self::logoFile()) {
                @copy(BASE_PATH . '/public/' . ltrim((string) Settings::get('brand.logo'), '/'), "$docroot/$logo");
            }
            $favicon = (string) Settings::get('brand.favicon', '');
            $hasFavicon = $favicon !== '' && is_file(BASE_PATH . '/public/' . ltrim($favicon, '/'));
            if ($hasFavicon) {
                @copy(BASE_PATH . '/public/' . ltrim($favicon, '/'), "$docroot/favicon.ico");
            } else {
                @unlink("$docroot/favicon.ico");
            }
        }
        self::writeBlankPage($docroot, $url);
        $https = str_starts_with($url, 'https://');
        $htaccess = "# " . self::MARKER . " — generated by the WebEdge control panel.\n"
            . "DirectoryIndex index.php\n"
            . "Options -Indexes\n"
            . "<IfModule mod_rewrite.c>\nRewriteEngine On\n"
            . ($https ? "RewriteCond %{HTTPS} !=on\nRewriteCond %{HTTP:X-Forwarded-Proto} !https\nRewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n" : '')
            . ($primary && !$hasFavicon ? "RewriteRule ^favicon\\.ico$ brand-mark.svg [L,T=image/svg+xml]\n" : '')
            . "RewriteRule (^|/)\\.(?!well-known/) - [F]\n"
            . "RewriteRule \\.webedge-bak$ - [F]\n"
            . "</IfModule>\n"
            . "<IfModule mod_headers.c>\nHeader set X-Robots-Tag \"noindex, nofollow\"\nHeader set X-Content-Type-Options \"nosniff\"\nHeader set Referrer-Policy \"same-origin\"\n"
            . ($https ? "Header always set Strict-Transport-Security \"max-age=31536000\"\n" : '')
            . "</IfModule>\n";
        file_put_contents("$docroot/.htaccess", $htaccess, LOCK_EX);
    }

    /** A tiny plugin that applies the panel's brand colour to the webmail. */
    private static function writeBrandingPlugin(): void
    {
        $dir = self::appDir() . '/plugins/webedge_branding';
        @mkdir($dir, 0755, true);
        file_put_contents("$dir/webedge_branding.php", <<<'PHP'
<?php
/** Generated by the WebEdge control panel: applies the brand colour. */
class webedge_branding extends rcube_plugin
{
    public function init()
    {
        $this->add_hook('render_page', [$this, 'render_page']);
        $this->add_hook('send_page', [$this, 'send_page']);
    }

    public function render_page($args)
    {
        $color = (string) rcmail::get_instance()->config->get('webedge_primary_color', '#2563eb');
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            return $args;
        }
        $css = ":root{--webedge:$color}"
            . ".button.mainaction,.btn-primary,.floating-action-buttons .button,#layout-menu .compose,.formbuttons .btn-primary{background-color:$color!important;border-color:$color!important;color:#fff!important}"
            . "a,a:hover,.listing li.selected>a,.nav-link.active,#layout-menu .selected a,.menu a.selected,.header .header-title{color:$color}"
            . "#layout-menu .selected,.listing li.selected,table.records-table tr.selected td,table.records-table tr.focused td{border-left-color:$color!important}"
            . "table.records-table tr.selected td,.listing li.selected{background-color:" . $color . "1a!important}"
            . ".unread .subject a,.flag .unread{color:$color}"
            . "input:focus,.form-control:focus,textarea:focus,select:focus{border-color:$color!important;box-shadow:0 0 0 .2rem " . $color . "40!important}";
        rcmail::get_instance()->output->add_header(html::tag('style', [], $css));
        return $args;
    }

    /** Swap Roundcube's favicon for the brand's. */
    public function send_page($args)
    {
        $icon = (string) rcmail::get_instance()->config->get('webedge_favicon', '');
        if ($icon !== '' && isset($args['content'])) {
            $args['content'] = preg_replace('#(<link[^>]+rel="(?:shortcut )?icon"[^>]+href=")[^"]+(")#i', '${1}' . htmlspecialchars($icon, ENT_QUOTES) . '${2}', $args['content']);
        }
        return $args;
    }
}
PHP);
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * Check a mailbox password by signing in to the IMAP server (used before a
     * password change requested from webmail). No PHP IMAP extension needed.
     */
    public static function imapLogin(string $user, string $password): bool
    {
        $host = (string) Settings::get('webmail.imap_host', self::DEFAULT_IMAP);
        if (!preg_match('#^(?:(ssl|tls)://)?([a-z0-9.\-]+)(?::(\d+))?$#i', $host, $m)) {
            return false;
        }
        $scheme = strtolower($m[1] ?? '');
        $port = (int) ($m[3] ?? 0) ?: ($scheme === 'ssl' ? 993 : 143);
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $m[2]]]);
        $fp = @stream_socket_client(($scheme === 'ssl' ? 'ssl://' : 'tcp://') . $m[2] . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("Could not connect to the mail server: $errstr");
        }
        stream_set_timeout($fp, 15);
        try {
            fgets($fp);
            if ($scheme === 'tls') {
                fwrite($fp, "a0 STARTTLS\r\n");
                if (!str_starts_with((string) self::imapResponse($fp, 'a0'), 'a0 OK') || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('The mail server refused a secure connection.');
                }
            }
            $q = static fn (string $s): string => '"' . addcslashes($s, "\"\\") . '"';
            if (preg_match('/[\r\n\x00]/', $user . $password)) {
                return false;
            }
            fwrite($fp, 'a1 LOGIN ' . $q($user) . ' ' . $q($password) . "\r\n");
            $ok = str_starts_with((string) self::imapResponse($fp, 'a1'), 'a1 OK');
            fwrite($fp, "a2 LOGOUT\r\n");
            return $ok;
        } finally {
            fclose($fp);
        }
    }

    private static function imapResponse($fp, string $tag): ?string
    {
        while (($line = fgets($fp)) !== false) {
            if (str_starts_with($line, $tag . ' ')) {
                return rtrim($line);
            }
        }
        return null;
    }

    /** Change a mailbox password on behalf of the webmail (after verifying the current one). */
    public static function changePasswordFromWebmail(string $address, string $current, string $new): void
    {
        $address = strtolower(trim($address));
        $mailbox = DB::one('SELECT id FROM mailboxes WHERE address = ?', [$address]);
        if (!$mailbox) {
            throw new RuntimeException('Unknown mailbox.');
        }
        if (!self::imapLogin($address, $current)) {
            Logger::security('mailbox_password_change', 'failure', "Webmail password change for $address rejected: current password incorrect");
            throw new RuntimeException('Current password incorrect.');
        }
        EmailService::changeMailboxPassword((int) $mailbox['id'], $new);
    }
}
