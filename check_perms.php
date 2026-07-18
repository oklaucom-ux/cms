<?php
session_start();
$_SESSION['login_id'] = 1; // Assuming 1 is Admin
$_SESSION['role'] = 'Admin';
require 'includes/db.php';
echo hasPermission($pdo, 'manage_reception') ? 'YES' : 'NO';
