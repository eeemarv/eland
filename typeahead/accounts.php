<?php
$rootpath = '../';
$page_access = 'guest';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();
$schema = $_GET['schema'] ?? '';
$status = $_GET['status'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

if (!$status)
{
	http_response_code(404);
	exit;
}

if ($s_guest && $status !== 'active')
{
	http_response_code(403);
	exit;
}

if(!$s_admin && !in_array($status, ['active', 'extern']))
{
	http_response_code(403);
	exit;
}

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

$fetched_users = $app['db']->fetchAll(
	'select letscode as c,
		name as n,
		extract(epoch from adate) as a,
		status as s,
		postcode as p,
		saldo as b,
		minlimit as min,
		maxlimit as max
	from ' . $tschema . '.users
	where status ' . $status_sql . '
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

$params = [
	'schema'	=> $schema,
	'status'	=> $status,
];

$app['typeahead']->set_thumbprint('accounts', $params, crc32($accounts));

header('Content-type: application/json');
echo $accounts;
