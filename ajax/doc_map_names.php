<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$map_names = array();

$elas_mongo->connect();

$cursor = $elas_mongo->docs->find(array('map_name' => array('$exists' => true)));

foreach ($cursor as $c)
{
	$map_names[] = $c['map_name'];
}

ob_clean();
header('Content-type: application/json');

echo json_encode($map_names);
