<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
//destroy session
session_start();
session_destroy();
unset($_SESSION);
header("Location: login.php");
?>
