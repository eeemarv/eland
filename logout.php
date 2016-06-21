<?php
$rootpath = '';
$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$_SESSION = [];

$cookie_params = session_get_cookie_params();
setcookie(session_name(), '', time() - 86400, $cookie_params['path'], $cookie_params['domain'],
	$cookie_params['secure'], $cookie_params['httponly']);

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Location: ' . $rootpath . 'login.php');
