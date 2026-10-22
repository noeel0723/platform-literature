<?php

return [
    'reset_enabled' => (bool) env('USER_ACCOUNT_RESET_ENABLED', false),
    'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'backup_directory' => storage_path('app/private/account-reset-backups'),
];
