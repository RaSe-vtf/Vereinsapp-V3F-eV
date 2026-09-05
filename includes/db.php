<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../private/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    die('Konfiguration fehlt. Bitte config.example.php nach private/config.php kopieren und ausfuellen.');
}

require_once $configPath;

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}
