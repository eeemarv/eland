<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$except = $_GET['except'] ?? 0;
$schema = $_GET['schema'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

if (!ctype_digit((string) $except))
{
	http_response_code(404);
	exit;
}

$usernames = [];

$st = $app['db']->prepare('select name
	from ' . $tschema . '.users
	where id <> ?
	order by name asc');

$st->bindValue(1, $except);
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
	'except'	=> $except,
];

$app['typeahead']->set_thumbprint('usernames', $params, crc32($usernames));

header('Content-type: application/json');

echo $usernames;
