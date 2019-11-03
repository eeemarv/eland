<?php declare(strict_types=1);

require_once __DIR__ . '/index.php';

/*
require_once __DIR__ . '/../include/web_legacy.php';

$token = $_GET['token'] ?? '';

if (strlen($token) > 10)
{
	header('Location: ' . $app->url('login_elas_token', [
		'system'		=> $system,
		'elas_token'	=> substr($token, 6),
	]));
	exit;
}

header('Location: ' . $app->url('login', [
	'system'		=> $system,
]));
exit;
*/
