<?php
$rootpath = '../';
$page_access = 'user';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();
$group_id = $_GET['group_id'] ?? 0;
$schema = $_GET['schema'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

if (!$group_id)
{
	http_response_code(404);
	exit;
}

$group = $app['db']->fetchAssoc('select *
	from ' . $tschema . '.letsgroups
	where id = ?', [$group_id]);

if (!$group || !$group['url'])
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

$domain = strtolower(parse_url($group['url'], PHP_URL_HOST));

$remote_schema = $app['groups']->get_schema($domain);

$params = [
	'group_id'	=> $group_id,
	'schema'	=> $schema,
];

if ($remote_schema)
{
	if ($app['db']->fetchColumn('select id
		from ' . $remote_schema . '.letsgroups
		where url = ?', [$app['base_url']]))
	{
		$fetched_users = $app['db']->fetchAll(
			'select letscode as c,
				name as n,
				extract(epoch from adate) as a,
				status as s,
				postcode as p,
				saldo as b,
				minlimit as min,
				maxlimit as max
			from ' . $remote_schema . '.users
			where status in (1, 2)
			order by id asc'
		);

		$accounts = [];

		foreach ($fetched_users as $account)
		{
			if ($account['s'] == 1)
			{
				unset($account['s']);
			}

			if ($account['max'] == 999999999)
			{
				unset($account['max']);
			}

			if ($account['min'] == -999999999)
			{
				unset($account['min']);
			}

			$accounts[] = $account;
		}

		$accounts = json_encode($accounts);

		$app['typeahead']->set_thumbprint('intersystem_accounts', $params, crc32($accounts));

		header('Content-type: application/json');
		echo $accounts;
		exit;
	}

	http_response_code(403);
	exit;
}

$accounts = $app['cache']->get($domain . '_typeahead_data', false);

if ($accounts)
{
	$app['typeahead']->set_thumbprint('intersystem_accounts', $params, crc32($accounts));

	header('Content-type: application/json');
	echo $accounts;
	exit;
}
else
{
	http_response_code(404);
	exit;
}
