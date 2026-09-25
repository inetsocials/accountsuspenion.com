<?php
/**
 * Copy to config.php in this folder and fill in. config.php is denied to the web by api/.htaccess.
 * Generate secrets on the server:  php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
 */
return [
    'APP_KEY'          => '{{APP_KEY}}',          // base64, 32 bytes. Back it up offline: without it cases cannot be read.
    'IP_SALT'          => '{{IP_SALT}}',
    'NOTIFY_TO'        => '{{STAFF_INBOX}}',
    'NOTIFY_FROM'      => '{{SENDER_ON_YOUR_DOMAIN}}',
    'TURNSTILE_SECRET' => '',
    // 'DATA_DIR'      => '/home/USER/domains/accountsuspension.com/as_data',
];
