<?php
$rootpath = '../';
$page_access = 'guest';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$group_id = $_GET['group_id'] ?? 'self';
$status = $_GET['status'] ?? 'active';

if ($s_guest && $status != 'active')
{
	http_response_code(403);
	exit;
}

if(!$s_admin && !in_array($status, ['active', 'extern']))
{
	http_response_code(403);
	exit;
}

if ($group_id == 'self')
{
	switch($status)
	{
		case 'extern':
			$status_sql = '= 7';
			break;
		case 'inactive':
			$status_sql = '= 0';
			break;
		case 'ip':
			$status_sql = '= 5';
			break;
		case 'im':
			$status_sql = '= 6';
			break;
		case 'active':
			$status_sql = 'in (1, 2)';
			break;
		default:
			http_response_code(404);
			exit;
	}

	$users = users_to_json($tschema, $status_sql);

	$app['typeahead']->invalidate_thumbprint('users_' . $status, false, crc32($users));

	header('Content-type: application/json');
	echo $users;
	exit;
}

$group = $app['db']->fetchAssoc('select *
	from ' . $tschema . '.letsgroups
	where id = ?', [$group_id]);

$group['domain'] = strtolower(parse_url($group['url'], PHP_URL_HOST));

if (!$group || $status != 'active')
{
	http_response_code(404);
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	header('Content-type: application/json');
	echo '{}';
	exit;
}

if ($app['groups']->get_schema($group['domain']))
{
	$remote_schema = $app['groups']->get_schema($group['domain']);

	if ($app['db']->fetchColumn('select id from ' . $remote_schema . '.letsgroups where url = ?', [$app['base_url']]))
	{
		$active_users = users_to_json($remote_schema);

		$app['typeahead']->invalidate_thumbprint('users_active', $group['domain'], crc32($active_users));

		header('Content-type: application/json');
		echo $active_users;
		exit;
	}

	http_response_code(403);
	exit;
}

$active_users = $app['cache']->get($group['domain'] . '_typeahead_data', false);

if ($active_users)
{
	$app['typeahead']->invalidate_thumbprint('users_active', $group['domain'], crc32($active_users));

	header('Content-type: application/json');
	echo $active_users;
	exit;
}
else
{
	http_response_code(404);
	exit;
}

function users_to_json($sch, $status_sql = 'in (1, 2)')
{
	global $app;

	$fetched_users = $app['db']->fetchAll(
		'select letscode as c,
			name as n,
			extract(epoch from adate) as a,
			status as s,
			postcode as p,
			saldo as b,
			minlimit as min,
			maxlimit as max
		from ' . $sch . '.users
		where status ' . $status_sql . '
		order by id asc'
	);

	$users = [];

	foreach ($fetched_users as $user)
	{
		if ($user['s'] == 1)
		{
			unset($user['s']);
		}

		if ($user['max'] == 999999999)
		{
			unset($user['max']);
		}

		if ($user['min'] == -999999999)
		{
			unset($user['min']);
		}

		$users[] = $user;
	}

	return json_encode($users);
}
