<?php
session_start();
$_SESSION['login_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['csrf_token'] = 'test';
$_POST = [
    'action' => 'register_visitor',
    'visitor_name' => 'John Doe',
    'host_id' => '2', // Valid user
    'expected_arrival' => '2026-07-20T10:00',
    'csrf_token' => 'test'
];
ob_start();
require 'controllers/reception_api.php';
echo "API OUT: " . ob_get_clean();
