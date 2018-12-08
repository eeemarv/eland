<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();
$schema = $_GET['schema'] ?? '';

if ($schema !== $tschema || !$schema)
{
	http_response_code(404);
	exit;
}

$map_names = [];

$st = $app['db']->prepare('select distinct data->>\'map_name\' as map_name
	from xdb.aggs
	where agg_type = \'doc\'
		and agg_schema = ?
		and data->>\'map_name\' <> \'\'
	order by data->>\'map_name\' asc');

$st->bindValue(1, $tschema);

$st->execute();

while ($row = $st->fetch())
{
	$map_names[] = $row['map_name'];
}

$map_names = json_encode($map_names);

$params = [
	'schema'	=> $schema,
];

$app['typeahead']->set_thumbprint('doc_map_names', $params, crc32($map_names));

header('Content-type: application/json');

echo $map_names;
