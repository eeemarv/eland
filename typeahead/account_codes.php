<?php

if (!($app['s_admin'] || $app['s_user']))
{
	exit;
}

$schema = $_GET['schema'] ?? '';

if ($schema !== $app['tschema'] || !$schema)
{
	http_response_code(404);
	exit;
}

$account_codes = [];

$st = $app['db']->prepare('select letscode
	from ' . $app['tschema'] . '.users
	order by letscode asc');

$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['letscode']))
	{
		continue;
	}

	$account_codes[] = $row['letscode'];
}

$account_codes = json_encode($account_codes);

$params = [
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('account_codes', $params, crc32($account_codes));

header('Content-type: application/json');

echo $account_codes;
