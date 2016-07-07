<?php
$rootpath = '../';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$map_names = [];

$rows = $exdb->get_many(['agg_schema' => $schema,
	'agg_type' => 'doc',
	'data->>\'map_name\'' => ['<>' => '']]);

if (count($rows))
{
	foreach ($rows as $row)
	{
		$map_names[] = $row['data']['map_name'];
	}
}
else
{
	$mdb->connect();

	$cursor = $mdb->docs->find(['map_name' => ['$exists' => true]]);

	foreach ($cursor as $c)
	{
		set_exdb('doc', $c);
		$map_names[] = $c['map_name'];
	}
}

$output = json_encode($map_names);

invalidate_typeahead_thumbprint('doc_map_names', false, crc32($output));

header('Content-type: application/json');

echo $output;
