<?php
$rootpath = '../';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$map_names = [];

$mdb->connect();

$cursor = $mdb->docs->find(['map_name' => ['$exists' => true]]);

foreach ($cursor as $c)
{
	$map_names[] = $c['map_name'];
}

$output = json_encode($map_names);

invalidate_typeahead_thumbprint('doc_map_names', false, crc32($output));

header('Content-type: application/json');

echo $output;
