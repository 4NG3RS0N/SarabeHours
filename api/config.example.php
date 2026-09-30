<?php
// Copy this file to config.php and fill in your real database credentials.
// NEVER commit config.php with real credentials to Git.

return [
    'db_host'    => '127.0.0.1',
    'db_port'    => '3306',
    'db_name'    => 'dd_support',
    'db_user'    => 'dd_support_user',
    'db_pass'    => 'change-me-strong-password',
    'db_charset' => 'utf8mb4',

    // Comma-separated allowed origins for CORS if the frontend is served
    // from a different domain/port than this API. Leave empty for same-origin.
    'allowed_origin' => 'http://localhost:5500',
];
