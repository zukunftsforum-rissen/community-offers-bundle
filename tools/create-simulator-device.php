#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$bundleDir = dirname(__DIR__);
$projectDir = dirname(dirname($bundleDir));
$envFile = $projectDir . '/.env';
$envLocalFile = $projectDir . '/.env.local';

if (!is_file($envFile)) {
    fwrite(STDERR, "Project .env not found: $envFile\n");
    exit(1);
}

echo "Bundle dir:  $bundleDir\n";
echo "Project dir: $projectDir\n";

$dotenv = new Dotenv();
$dotenv->loadEnv($envFile);

$key = 'CO_SIMULATOR_DEVICE_TOKEN';
$deviceName = 'shed-simulator';

$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);

$envContent = is_file($envLocalFile) ? (string) file_get_contents($envLocalFile) : '';

$pattern = '/^' . preg_quote($key, '/') . '=.*/m';

if (preg_match($pattern, $envContent)) {
    $envContent = (string) preg_replace($pattern, $key . '=' . $token, $envContent);
    echo "Updated $key in .env.local\n";
} else {
    $envContent = rtrim($envContent) . PHP_EOL . $key . '=' . $token . PHP_EOL;
    echo "Added $key to .env.local\n";
}

file_put_contents($envLocalFile, $envContent);

$dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;

if (!$dbUrl) {
    fwrite(STDERR, "DATABASE_URL not found in environment.\n");
    exit(1);
}

$conn = DriverManager::getConnection([
    'url' => $dbUrl,
]);

$existing = $conn->fetchAssociative(
    'SELECT id FROM tl_co_device WHERE name = ?',
    [$deviceName]
);

$data = [
    'name' => $deviceName,
    'apiTokenHash' => $hash,
    'enabled' => '1',
    'tstamp' => time(),
];

if ($existing) {
    $conn->update(
        'tl_co_device',
        [
            'apiTokenHash' => $hash,
            'enabled' => '1',
            'tstamp' => time(),
        ],
        ['id' => $existing['id']]
    );

    echo "Updated device: $deviceName (id {$existing['id']})\n";
} else {
    $conn->insert('tl_co_device', $data);
    echo "Created device: $deviceName\n";
}

echo "Token written to: $envLocalFile\n";
echo "Device token hash stored in tl_co_device\n";
