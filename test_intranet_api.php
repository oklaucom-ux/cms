<?php 
session_start();
chdir("controllers");
$_GET["action"]="list"; 
$_SESSION["login_id"]="admin"; 
$_SESSION["role"]="Super Admin"; 
require "intranet_api.php";
