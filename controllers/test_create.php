<?php
require_once '../includes/db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create';
$_POST['question'] = 'test question';
$_POST['csrf_token'] = $_SESSION['csrf_token'] ?? 'fake';
$_SESSION['csrf_token'] = $_POST['csrf_token']; // bypass csrf
$_SESSION['role'] = 'Admin';
$_SESSION['login_id'] = '1';
require 'save_survey.php';
