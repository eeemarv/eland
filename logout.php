<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
//destroy session
session_destroy();
unset($_SESSION);
header('Location: login.php');
