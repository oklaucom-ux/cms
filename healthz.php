<?php
// healthz.php — Health Check Endpoint for Load Balancers & Monitoring
// Usage: GET /healthz.php — returns JSON status
// No session, no auth, no CSRF — must be fast and stateless.

header('Content-Type: application/json');
header('Cache-Control: no-store');

$status = 'ok';
$checks = [];

// 1. Database connectivity
try {
    $db_url = getenv('DATABASE_URL');
    $db_host = getenv('DB_HOST');

    if (!empty($db_url) && str_starts_with($db_url, 'mysql://')) {
        $parsed = parse_url($db_url);
        $dsn = "mysql:host=" . ($parsed['host'] ?? '127.0.0.1') . ";port=" . ($parsed['port'] ?? 3306) . ";dbname=" . ltrim($parsed['path'], '/');
        $pdo = new PDO($dsn, $parsed['user'] ?? '', $parsed['pass'] ?? '', [PDO::ATTR_TIMEOUT => 3]);
    } elseif (!empty($db_host)) {
        $dsn = "mysql:host={$db_host};port=" . (getenv('DB_PORT') ?: 3306) . ";dbname=" . (getenv('DB_NAME') ?: 'cms');
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_TIMEOUT => 3]);
    } else {
        $db_file = __DIR__ . '/database.sqlite';
        $pdo = new PDO("sqlite:" . $db_file);
    }
    $pdo->query("SELECT 1");
    $checks['database'] = 'connected';
} catch (Exception $e) {
    $checks['database'] = 'unreachable';
    $status = 'degraded';
}

// 2. Disk space
$freeBytes = @disk_free_space(__DIR__);
$checks['disk_free_mb'] = $freeBytes ? round($freeBytes / 1024 / 1024) : 'unknown';
if ($freeBytes && $freeBytes < 100 * 1024 * 1024) {
    $status = 'degraded';
}

// 3. PHP info
$checks['php_version'] = PHP_VERSION;
$checks['memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 1);
$checks['memory_limit'] = ini_get('memory_limit');

http_response_code($status === 'ok' ? 200 : 503);
echo json_encode([
    'status' => $status,
    'timestamp' => gmdate('c'),
    'checks' => $checks,
], JSON_PRETTY_PRINT);
