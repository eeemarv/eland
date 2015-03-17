<?php
ob_start();
$rootpath = "./";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($s_id))
{
	header('Location: ' . $rootpath . 'login.php');
	exit;
}


