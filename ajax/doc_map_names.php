<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$elas_mongo->connect();

$cursor = $elas_mongo->docs->find(array('map_name' => array('$exists' => true)));

ob_clean();
header('Content-type: application/json');

echo json_encode(iterator_to_array($cursor));
