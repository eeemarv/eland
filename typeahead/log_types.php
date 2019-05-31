<?php

if (!$app['s_admin'])
{
	exit;
}

$schema = $_GET['schema'] ?? '';

if ($schema !== $app['tschema'] || !$schema)
{
	http_response_code(404);
	exit;
}

$log_types = [];

$st = $app['db']->prepare('select distinct type
	from xdb.logs
	where schema = ?
	order by type asc');

$st->bindValue(1, $app['tschema']);

$st->execute();

while ($row = $st->fetch())
{
	$log_types[] = $row['type'];
}

$log_types = json_encode($log_types);

$params = [
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('log_types', $params, crc32($log_types), 345600); // 4 days

header('Content-type: application/json');

echo $log_types;
