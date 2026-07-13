<?php
// update.php - Web endpoint to run migrations safely
session_start();
require_once 'includes/db.php';

echo "<h1>System Update & Database Migration</h1>";
echo "<pre>";
echo "Running migrations...\n\n";

// Execute the migrate.php logic
$output = shell_exec("php migrate.php 2>&1");
echo htmlspecialchars($output);

echo "</pre>";
echo "<br><a href='dashboard.php'>Return to Dashboard</a>";
