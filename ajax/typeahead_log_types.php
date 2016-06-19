<?php
$rootpath = '../';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$map_names = [];

$mdb->connect();

$types = $mdb->logs->distinct('type');

$output = json_encode($types);

invalidate_typeahead_thumbprint('log_types', false, crc32($output), 345600); // 4 days

header('Content-type: application/json');

echo $output;
