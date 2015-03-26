<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$users = $db->GetArray(
	'SELECT letscode as c,
		fullname as n,
		extract(epoch from adate) as e,
		status as s,
		postcode as p
	FROM users
	WHERE status IN (1, 2)'
);

ob_clean();

$new_user_seconds = readconfigfromdb('newuserdays') * 86400;
$now = time();
$active_users = array();

foreach ($users as $user)
{
	$e = $user['e'];
	unset($user['e']);

	if ($e + $new_user_seconds > $now && $user['s'] != 2)
	{
		$user['s'] = 3;
	}

	if ($user['s'] == 1)
	{
		unset($user['s']);
	}

	$active_users[] = $user;
}

header('Content-type: application/json');
echo json_encode($active_users);
