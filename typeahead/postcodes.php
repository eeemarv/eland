<?php
$rootpath = '../';
$page_access = 'user';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$schema = $_GET['schema'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

$postcodes = [];

$st = $app['db']->prepare('select distinct postcode
	from ' . $tschema . '.users
	order by postcode asc');

$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['postcode']))
	{
		continue;
	}

	$postcodes[] = $row['postcode'];
}

$postcodes = json_encode($postcodes);

$params = [
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('postcodes', $params, crc32($postcodes));

header('Content-type: application/json');

echo $postcodes;
