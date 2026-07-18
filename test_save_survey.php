<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create';
$_POST['question'] = 'Test?';
$_SESSION = ['role' => 'Admin', 'login_id' => 1];
$_SERVER['PHP_SELF'] = '/controllers/save_survey.php'; // bypass CSRF whitelist problem

require 'controllers/save_survey.php';
