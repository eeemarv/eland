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

$accounts = $app['cache']->get($domain . '_typeahead_data', false);

if (!$accounts)
{
	$app['monolog']->debug('typeahead/elas_intersystem_accounts: empty for id ' .
		$group_id . ', url: ' . $group['url'], ['schema' => $tschema]);
	http_response_code(404);
	exit;
}

$params = [
	'group_id'	=> $group_id,
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('elas_intersystem_accounts',
	$params,
	crc32($accounts)
);

header('Content-type: application/json');
echo $accounts;
