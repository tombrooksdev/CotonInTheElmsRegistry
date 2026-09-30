<?php
// Copy to config.php. Keep this file OUTSIDE the web root if you can.
return [
    'db' => ['host' => 'localhost', 'name' => 'coton_register', 'user' => 'coton_user', 'pass' => 'CHANGE_ME'],
    'site_url' => 'https://cotonintheelms.co.uk/register',
    'ip_salt' => 'CHANGE_ME_RANDOM_STRING',
    'admin_password_hash' => 'PASTE_OUTPUT_OF_password_hash', // php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
    'graph' => [
        'tenant_id' => 'CHANGE_ME',
        'client_id' => 'CHANGE_ME',
        'client_secret' => 'CHANGE_ME',
        'sender' => 'noreply@bssweb.co.uk',
    ],
    'confirm_email_subject' => 'Please confirm your Coton in the Elms business listing',
    'consent_text' => 'Yes, email me updates about the Coton in the Elms business register. You can unsubscribe at any time.',
];
