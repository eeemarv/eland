<?php
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$map_names = array();

$mdb->connect();

$types = $mdb->logs->distinct('type');

$output = json_encode($types);

invalidate_typeahead_thumbprint('log_types', false, crc32($output), 86400);

header('Content-type: application/json');

echo $output;
