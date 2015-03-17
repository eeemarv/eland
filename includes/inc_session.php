<?php
/*
 * get session name from environment variable ELAS_DOMAIN_SESSION_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * ELAS_DOMAIN_SESSION_E___EXAMPLE__COM = <session_name>
 *
 * + the session name has to be set to the color name of the database!
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */

if (!isset($session_name))
{
	$session_name = str_replace(':', '', $_SERVER['HTTP_HOST']);
	$session_name = str_replace('.', '__', $session_name);
	$session_name = str_replace('-', '___', $session_name);
	$session_name = strtoupper($session_name);
	$session_name = getenv('ELAS_DOMAIN_SESSION_' . $session_name);

	if (!$session_name)
	{
		$db_url = getenv('DATABASE_URL');

		foreach ($_ENV as $env => $value)
		{
			if ($env != 'DATABASE_URL' && $value == $db_url) //strpos('HEROKU_POSTGRESQL_', $env) === 0)
			{
				$session_name = str_replace('HEROKU_POSTGRESQL_', '', $env);
				$session_name = str_replace('_URL', '', $session_name);
				break;
			}
		}

		unset ($db_url);
	}

	session_name($session_name);
}

session_start();

$s_id = $_SESSION['id'];
$s_name = $_SESSION['name'];
$s_letscode = $_SESSION['letscode'];
$s_accountrole = $_SESSION['accountrole'];


if (!$role || (!in_array($role, array('admin', 'user', 'guest', 'anonymous')))){
	header(' ', true, 500);
	include '500.html';
	exit;
}

if ($role != 'anonymous' && (!isset($s_id) || !$s_accountrole || !$s_name)){
	header('Location: ../login.php?location=' . urlencode($_SERVER['REQUEST_URI']));
	exit;
}

if ((!$allow_anonymous_post && $s_accountrole == 'anonymous' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($s_accountrole == 'guest' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($role == 'admin' && $s_accountrole != 'admin')
	|| ($role == 'user' && !in_array($s_accountrole, array('admin', 'user')))
	|| ($role == 'guest' && !in_array($s_accountrole, array('admin', 'user', 'guest')))){
	header('HTTP/1.1 403 Unauthorized', true, 403);
	include '403.html';
	exit;
}

