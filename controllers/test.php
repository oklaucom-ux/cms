<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create';
$_POST['question'] = 'Test?';
$_SESSION = ['role' => 'Admin', 'login_id' => 1, 'csrf_token' => 'dummy123'];
$_POST['csrf_token'] = 'dummy123';
$_SERVER['PHP_SELF'] = '/controllers/save_survey.php';

require 'save_survey.php';
