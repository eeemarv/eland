<?php
$rootpath = '../';
$page_access = 'user';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();
$schema = $_GET['schema'] ?? '';
$remote_schema = $_GET['remote_schema'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

if (!$remote_schema)
{
	http_response_code(404);
	exit;
}

if (!isset($eland_interlets_groups[$remote_schema]))
{
	$app['monolog']->debug('typeahead/eland_intersystem_accounts: ' .
		$remote_schema . ' not valid', ['schema' => $tschema]);
	http_response_code(404);
	exit;
}

$params = [
	'remote_schema'	=> $remote_schema,
	'schema'		=> $schema,
];

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

$app['typeahead']->set_thumbprint('eland_intersystem_accounts',
	$params,
	crc32($accounts)
);

header('Content-type: application/json');
echo $accounts;
