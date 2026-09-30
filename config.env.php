<?php
// Container config: everything comes from environment variables (see .env.example).
$env = fn(string $k, string $d = '') => getenv($k) !== false ? getenv($k) : $d;

return [
    'db' => [
        'host' => $env('DB_HOST', 'db'),
        'name' => $env('DB_NAME', 'coton_register'),
        'user' => $env('DB_USER', 'coton'),
        'pass' => $env('DB_PASS'),
    ],
    'site_url' => $env('SITE_URL', 'http://localhost:8080'),
    'ip_salt' => $env('IP_SALT'),
    'admin_password_hash' => $env('ADMIN_PASSWORD_HASH'),
    'graph' => [
        'tenant_id' => $env('MICROSOFT_TENANT_ID'),
        'client_id' => $env('MICROSOFT_CLIENT_ID'),
        'client_secret' => $env('MICROSOFT_CLIENT_SECRET'),
        'sender' => $env('MICROSOFT_SENDER_EMAIL'),
    ],
    'confirm_email_subject' => $env('CONFIRM_EMAIL_SUBJECT', 'Please confirm your Coton in the Elms business listing'),
    'consent_text' => 'Yes, email me updates about the Coton in the Elms business register. You can unsubscribe at any time.',
];
