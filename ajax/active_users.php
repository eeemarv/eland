<?php
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$letsgroup_id = $_GET['letsgroup_id'];

if (!$letsgroup_id || $letsgroup_id == 'self')
{
	$users = $db->fetchAll(
		'SELECT letscode as c,
			name as n,
			extract(epoch from adate) as e,
			status as s,
			postcode as p
		FROM users
		WHERE status IN (1, 2)'
	);

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

	$active_users = json_encode($active_users);

	header('Content-type: application/json');
	echo $active_users;
	exit;
}

$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

if (!$group)
{
	http_response_code(404);
	exit;
}

switch($group['apimethod'])
{
	case 'elassoap':

		ob_clean();
		header('Content-type: application/json');
		echo $redis->get($group['url'] . '_typeahead_data');
		exit;

	default:
		ob_clean();
		header('Content-type: application/json');
		echo json_encode(array());
		exit;
}
