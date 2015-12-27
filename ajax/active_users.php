<?php
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$letsgroup_id = $_GET['letsgroup_id'];

if (!$letsgroup_id || $letsgroup_id == 'self')
{
	users_to_json();
}

$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

if (!$group)
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
		users_to_json($remote_schema);
	}

	http_response_code(403);
	exit;
}

header('Content-type: application/json');
echo $redis->get($group['url'] . '_typeahead_data');
exit;

function users_to_json($remote_schema = null)
{
	global $db;

	$table_schema = (isset($remote_schema)) ? $remote_schema . '.' : '';

	$users = $db->fetchAll(
		'SELECT letscode as c,
			name as n,
			extract(epoch from adate) as e,
			status as s,
			postcode as p
		FROM ' . $table_schema . 'users
		WHERE status IN (1, 2)'
	);

	if (isset($remote_schema))
	{
		$new_user_seconds = readconfigfromdb('newuserdays', $remote_schema) * 86400;
	}
	else
	{
		$new_user_seconds = readconfigfromdb('newuserdays') * 86400;
	}

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
