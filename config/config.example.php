<?php
// Copy to config/config.php (the web installer does this for you).
return [
    'app' => [
        // Full public URL of the panel, without a trailing slash.
        'url' => 'https://panel.example.com',
        // 32 random bytes, base64 encoded. Generate: php -r "echo 'base64:'.base64_encode(random_bytes(32));"
        // Encrypts stored secrets (SMTP password, provider API tokens). Never change it after install.
        'key' => 'base64:CHANGE_ME',
        'timezone' => 'Asia/Kolkata',
        'debug' => false,
        // Set true only behind a trusted reverse proxy / Cloudflare so client IPs are read from headers.
        'trust_proxy' => false,
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'webedge',
        'user' => 'webedge',
        'pass' => '',
    ],
];
