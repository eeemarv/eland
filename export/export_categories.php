<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$categories = $db->GetArray('select * from categories');

header("Content-disposition: attachment; filename=elas-categories-".date("Y-m-d").".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

echo '"name","id_parent","description","cdate","fullname","leafnote"';
echo "\r\n";

foreach($categories as $value)
{
	echo '"';
	echo $value['name'];
	echo '","';
	echo $value['id_parent'];
	echo '","';
	echo $value['description'];
	echo '","';
	echo $value['cdate'];
	echo '","';
	echo $value['fullname'];
	echo '","';
	echo $value['leafnote'];
	echo '"';
	echo "\r\n";
}
