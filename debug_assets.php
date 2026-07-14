<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/assets.php';
$_SERVER['HTTPS'] = 'off';

session_start();
$_SESSION['login_id'] = 'admin';
$_SESSION['role'] = 'Super Admin';

try {
    require 'assets.php';
} catch (Throwable $e) {
    echo "\n\nCRASH:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
