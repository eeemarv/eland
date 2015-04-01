<?php
/*
 * get session name from environment variable ELAS_SCHEMA_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * ELAS_SCHEMA_E___EXAMPLE__COM = <session_name>
 *
 * + the session name is the schema name !
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */

if (!isset($schema))
{
	$schema = str_replace(':', '', $_SERVER['HTTP_HOST']);
	$schema = str_replace('.', '__', $schema);
	$schema = str_replace('-', '___', $schema);
	$schema = strtoupper($schema);
	$schema = getenv('ELAS_SCHEMA_' . $schema);
}

if (!$schema)
{
	http_response_code(404);
	include $rootpath. '404.html';
	exit;
}


session_name($schema);
session_start();

$s_id = $_SESSION['id'];
$s_name = $_SESSION['name'];
$s_letscode = $_SESSION['letscode'];
$s_accountrole = $_SESSION['accountrole'];


if (!isset($role) || !$role || (!in_array($role, array('admin', 'user', 'guest', 'anonymous'))))
{
	http_response_code(500);
	include $rootpath . '500.html';
	exit;
}

if ($role != 'anonymous' && (!isset($s_id) || !$s_accountrole || !$s_name))
{
	header('Location: ../login.php?location=' . urlencode($_SERVER['REQUEST_URI']));
	exit;
}

if ((!isset($allow_anonymous_post) && $s_accountrole == 'anonymous' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($s_accountrole == 'guest' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($role == 'admin' && $s_accountrole != 'admin')
	|| ($role == 'user' && !in_array($s_accountrole, array('admin', 'user')))
	|| ($role == 'guest' && !in_array($s_accountrole, array('admin', 'user', 'guest'))))
{
	http_response_code(403);
	include $rootpath . '403.html';
	exit;
}

