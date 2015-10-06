<?php
ob_start();

$rootpath = './';
$role = 'user';

require_once $rootpath . 'includes/inc_default.php';

$location = $_GET['location'];
$location = ($location) ? urldecode($location) : 'index.php';
$location = ($location == 'login.php') ? 'index.php' : $location;
$location = ($location == 'logout.php') ? 'index.php' : $location;

if ($_SESSION['rights'] == 'admin')
{
	if ($s_user)
	{
		$_SESSION['accountrole'] = 'admin';
	}
	else
	{
		$_SESSION['accountrole'] = 'user';
	}
}

header('Location: ' . $location);
