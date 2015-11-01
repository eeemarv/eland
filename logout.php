<?php
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

session_destroy();
$cookie_params = session_get_cookie_params();
setcookie(session_name(), '', 0, $cookie_params['path'], $cookie_params['domain'],
	$cookie_params['secure'], $cookie_params['httponly']);
$_SESSION = array();
redirect_login();
