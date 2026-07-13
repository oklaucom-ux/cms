<?php
// migrate.php
require_once __DIR__ . '/includes/db.php';

echo "Database Migration System Initialized.\n";

$ai = $use_mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY $ai,
        version VARCHAR(255) UNIQUE NOT NULL,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    die("Failed to initialize migrations table: " . $e->getMessage() . "\n");
}

$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

$executed = [];
$stmt = $pdo->query("SELECT version FROM schema_migrations");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $executed[] = $row['version'];
}

$pending = 0;

foreach ($files as $file) {
    $version = basename($file, '.php');
    if (!in_array($version, $executed)) {
        $pending++;
        echo "Running migration: $version... ";
        
        $queries = require $file;
        if (!is_array($queries)) {
            echo "FAILED (Must return an array of queries)\n";
            continue;
        }

        $pdo->beginTransaction();
        $hasError = false;
        
        foreach ($queries as $q) {
            if (empty(trim($q))) continue;
            if (!$use_mysql) {
                $q = str_ireplace('AUTO_INCREMENT', 'AUTOINCREMENT', $q);
            }
            try {
                $pdo->exec($q);
            } catch (PDOException $e) {
                $msg = strtolower($e->getMessage());
                // Ignore safe errors if this is a legacy schema file (001 or 002)
                $isSafeError = false;
                if ($version === '001_baseline' || $version === '002_scattered_schemas') {
                    if (strpos($msg, 'duplicate column') !== false || 
                        strpos($msg, 'already exists') !== false || 
                        strpos($msg, 'duplicate key') !== false) {
                        $isSafeError = true;
                    }
                }
                
                if (!$isSafeError) {
                    echo "\n  -> Error on query: " . substr($q, 0, 50) . "...\n";
                    echo "  -> " . $e->getMessage() . "\n";
                    $hasError = true;
                    break;
                }
            }
        }

        if ($hasError) {
            $pdo->rollBack();
            echo "ROLLBACK.\n";
            exit(1);
        } else {
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
            $stmt->execute([$version]);
            $pdo->commit();
            echo "SUCCESS.\n";
        }
    }
}

if ($pending === 0) {
    echo "Nothing to migrate. Database is up to date.\n";
} else {
    echo "Successfully ran $pending migration(s).\n";
}
