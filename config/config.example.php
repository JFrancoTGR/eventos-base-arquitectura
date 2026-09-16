<?php

return [
    'app' => [
        'env' => 'development',
        'debug' => true,
        'timezone' => 'America/Mexico_City',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'database_name',
        'user' => 'database_user',
        'pass' => 'database_password',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'rate_limit_window' => 300,
        'rate_limit_max_attempts' => 20,
        'app_key' => 'change_me',
    ],
];