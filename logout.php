<?php

$page_access = 'guest';
require_once __DIR__ . '/include/web.php';

$logins = $app['session']->get('logins') ?? [];

foreach($logins as $sch => $uid)
{
	$app['xdb']->set('logout', $uid, ['time' => time()], $sch);
}

$app['session']->invalidate();

/*
$cookie_params = session_get_cookie_params();
setcookie(session_name(), '', time() - 86400, $cookie_params['path'], $cookie_params['domain'],
	$cookie_params['secure'], $cookie_params['httponly']);

session_destroy();
*/

$app['monolog']->info('user logged out');

header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Location: ' . $rootpath . 'login.php');
exit;
