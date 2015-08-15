<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

header("Content-disposition: attachment; filename=elas-contact-".date("Y-m-d").".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

$contacts = $db->GetArray('select c.*, tc.abbrev, u.letscode, u.name
	from contact c, type_contact tc, users u
	where c.id_type_contact = tc.id
		and c.id_user = u.id');

echo '"letscode", "username", "abbrev", "comments", "value", "flag_public"';
echo "\r\n";

foreach($contacts as $key => $value)
{
	echo '"';
	echo $value["letscode"];
	echo '","';
	echo $value["name"];
	echo '","';
	echo $value["abbrev"];
	echo '","';
	echo $value["comments"];
	echo '","';
	echo $value["value"];
	echo '","';
	echo $value["flag_public"];
	echo '"';
	echo "\r\n";
	}
