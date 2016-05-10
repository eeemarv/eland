<?php
$rootpath = '../';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$letsgroup_id = ($_GET['letsgroup_id']) ?: 'self';
$status = ($_GET['status']) ?: 'active';

if ($s_guest && $status != 'active' && $letsgroup_id != 'self')
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
		case 'active':
			$status_sql = 'in (1, 2)';
			break;
		default:
			http_response_code(404);
			exit;
	}

	$users = users_to_json($schema, $status_sql);

	invalidate_typeahead_thumbprint('users_' . $status, $base_url, crc32($users));

	header('Content-type: application/json');
	echo $users;
	exit;
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

		invalidate_typeahead_thumbprint('users_active', $group['url'], crc32($active_users));

		header('Content-type: application/json');
		echo $active_users;
		exit;
	}

	http_response_code(403);
	exit;
}

$active_users = $redis->get($group['url'] . '_typeahead_data');

invalidate_typeahead_thumbprint('users_active', $group['url'], crc32($active_users));

header('Content-type: application/json');
echo $active_users;
exit;


/*
 *
 */
function users_to_json($schema, $status_sql = 'in (1, 2)')
{
	global $db;

	$fetched_users = $db->fetchAll(
		'SELECT letscode as c,
			name as n,
			extract(epoch from adate) as a,
			status as s,
			postcode as p
		FROM ' . $schema . '.users
		WHERE status ' . $status_sql
	);

	$users = array();

	$new_user_days = readconfigfromdb('newuserdays', $schema);

	foreach ($fetched_users as $user)
	{
		$user['nd'] = $new_user_days;

		if ($user['s'] == 1)
		{
			unset($user['s']);
		}

		$users[] = $user;
	}

	return json_encode($users);
}

