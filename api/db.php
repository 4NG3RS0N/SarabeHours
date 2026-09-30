<?php
declare(strict_types=1);

function dd_get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => 'CONFIG_MISSING',
            'message' => 'api/config.php not found. Copy api/config.example.php to api/config.php and fill in your DB credentials.',
        ]);
        exit;
    }

    $config = require $configPath;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_port'] ?? '3306',
        $config['db_name'],
        $config['db_charset'] ?? 'utf8mb4'
    );

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        // Never leak raw DB error/stack trace to the client.
        error_log('DB connection failed: ' . $e->getMessage());
        echo json_encode([
            'error'   => 'DATABASE_UNAVAILABLE',
            'message' => 'Impossible de se connecter à la base de données.',
        ]);
        exit;
    }

    return $pdo;
}

function dd_get_config(): array
{
    return require __DIR__ . '/config.php';
}
