<?php

$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    throw new RuntimeException('Application configuration not found.');
}

$config = require $configPath;

if (!isset($config['app'], $config['database'], $config['security'])) {
    throw new RuntimeException('Invalid application configuration.');
}

date_default_timezone_set(
    isset($config['app']['timezone'])
        ? $config['app']['timezone']
        : 'America/Mexico_City'
);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailNormalizer.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once __DIR__ . '/RegistrationException.php';
require_once __DIR__ . '/EventRepository.php';
require_once __DIR__ . '/RegistrationService.php';
require_once __DIR__ . '/RateLimiter.php';

$pdo = Database::getConnection($config['database']);