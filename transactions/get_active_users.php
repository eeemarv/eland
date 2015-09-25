<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$letsgroup_id = $_GET['letsgroup_id'];

if (!isset($letsgroup_id))
{
	http_response_code(404);
	exit;
}

$letsgroup = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

if (!$letsgroup)
{
	http_response_code(404);
	exit;
}

switch($letsgroup['apimethod'])
{
	case 'elassoap':

		ob_clean();
		header('Content-type: application/json');
		echo $redis->get($letsgroup['url'] . '_typeahead_data');
		exit;

	case 'internal':
		$users = $db->fetchAll(
			'SELECT letscode as c,
				fullname as n,
				extract(epoch from adate) as e,
				status as s,
				postcode as p
			FROM users
			WHERE status IN (1, 2)'
		);

		$new_user_seconds = readconfigfromdb('newuserdays') * 86400;

		ob_clean();

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

	default:
		ob_clean();
		header('Content-type: application/json');
		echo json_encode(array());
		exit;
}
