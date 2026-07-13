<?php
// update.php - Web endpoint to run migrations safely
session_start();
require_once 'includes/db.php';

echo "<h1>System Update & Database Migration</h1>";
echo "<pre>";
echo "Running migrations...\n\n";

// Capture output of migrate.php instead of using shell_exec
ob_start();
try {
    require_once 'migrate.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
$output = ob_get_clean();
echo htmlspecialchars($output);

echo "</pre>";
echo "<br><a href='dashboard.php'>Return to Dashboard</a>";
