<?php
require_once '../includes/db.php';
require_once '../includes/permissions.php';
requirePermission($pdo, 'view_intranet');
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'submit_response';
$_POST['survey_id'] = 1;
$_POST['score'] = 10;
$_POST['csrf_token'] = $_SESSION['csrf_token'] ?? 'fake';
$_SESSION['csrf_token'] = $_POST['csrf_token']; // bypass csrf
require 'save_survey.php';
