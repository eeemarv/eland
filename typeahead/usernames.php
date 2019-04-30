<?php
$rootpath = '../';
$app['page_access'] = 'user';
require_once __DIR__ . '/../include/web.php';

$schema = $_GET['schema'] ?? '';

if ($schema !== $app['tschema'] || !$schema)
{
	http_response_code(404);
	exit;
}

$usernames = [];

$st = $app['db']->prepare('select name
	from ' . $app['tschema'] . '.users
	order by name asc');

$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['name']))
	{
		continue;
	}

	$usernames[] = $row['name'];
}

$usernames = json_encode($usernames);

$params = [
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('usernames', $params, crc32($usernames));

header('Content-type: application/json');

echo $usernames;
