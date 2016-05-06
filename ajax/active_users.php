<?php
$rootpath = '../';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$letsgroup_id = ($_GET['letsgroup_id']) ?: 'self';
$status = ($_GET['status']) ?: 'active';

if ($s_guest & $status != 'active')
{
	http_reponse_code(403);
	exit;
}

if ($letsgroup_id == 'self')
{
	switch($status)
	{
		case 'extern':
			$status_sql = '= 7';
			break;
		case 'active_and_extern':
			$status_sql = 'in (1, 2, 7)';
			break;
		case 'active':
			$status_sql = 'in (1, 2)';
		default:
			http_response_code(404);
			exit;
	}

	$users_json = users_to_json($schema, $status_sql);
	set_thumbprint_and_response($users_json, $letsgroup_id, $status);
}

$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

if (!$group || $status != 'active')
{
	http_response_code(404);
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	header('Content-type: application/json');
	echo json_encode(array());
	exit;
}

list($schemas, $domains) = get_schemas_domains(true);

if ($schemas[$group['url']])
{
	$remote_schema = $schemas[$group['url']];

	if ($db->fetchColumn('select id from ' . $remote_schema . '.letsgroups where url = ?', array($base_url)))
	{
		$active_users = users_to_json($remote_schema);
		set_thumbprint_and_response($active_users, $letsgroup_id);
	}

	http_response_code(403);
	exit;
}

$active_users = $redis->get($group['url'] . '_typeahead_data');
set_thumbprint_and_response($active_users, $letsgroup_id);

/*
 *
 */
function users_to_json($schema, $status_sql = 'in (1, 2)')
{
	global $db, $redis;

	$fetched_users = $db->fetchAll(
		'SELECT letscode as c,
			name as n,
			extract(epoch from adate) as e,
			status as s,
			postcode as p
		FROM ' . $schema . '.users
		WHERE status ' . $status_sql
	);

	$new_user_seconds = readconfigfromdb('newuserdays', $schema) * 86400;

	$now = time();
	$users = array();

	foreach ($fetched_users as $user)
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

		$users[] = $user;
	}

	return json_encode($users);
}

/**
 *
 */

function set_thumbprint_and_response($users, $letsgroup_id, $status)
{
	global $schema, $redis;

	$redis_key = $schema . '_typeahead_thumbprint_' . $letsgroup_id . '_' . $status;
	$crc32 = crc32($users);

	$redis->set($redis_key, $crc32);
	$redis->expire($redis_key, 5184000); // 60 days

	header('Content-type: application/json');
	echo $users;
	exit;
}
