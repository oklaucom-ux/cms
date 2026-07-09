<?php
session_start();
$_SESSION['last_activity'] = time();
echo json_encode(['ok' => true]);
