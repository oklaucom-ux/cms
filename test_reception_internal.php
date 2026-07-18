<?php
require_once 'includes/db.php';
$_POST = [
    'action' => 'register_visitor',
    'visitor_name' => 'John Doe',
    'host_id' => '1',
    'expected_arrival' => '2026-07-20T10:00'
];
// bypass permission and csrf
$_SESSION['login_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['csrf_token'] = 'test';
$_POST['csrf_token'] = 'test';
ob_start();
require 'controllers/reception_api.php';
$out = ob_get_clean();
echo "API OUTPUT: " . $out;
