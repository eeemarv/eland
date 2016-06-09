<?php
$rootpath = '';
$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

if ($s_accountrole == 'guest' || count($logins) == 1)
{
	session_destroy();
	$cookie_params = session_get_cookie_params();
	setcookie(session_name(), '', 0, $cookie_params['path'], $cookie_params['domain'],
		$cookie_params['secure'], $cookie_params['httponly']);

	$_SESSION = array();
}
else
{
	unset($_SESSION['logins'][$schema], $_SESSION['roles'][$schema]);
}

header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Location: ' . $rootpath . 'login.php');
