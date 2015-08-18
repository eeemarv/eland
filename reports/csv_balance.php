<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

include 'inc_balance.php';

$user_date = $_GET['date'];
$user_prefix = $_GET['prefix'];

header('Content-disposition: attachment; filename=marva-balances-'.$user_date .'.csv');
header('Content-Type: application/force-download');
header('Content-Transfer-Encoding: binary');
header('Pragma: no-cache');
header('Expires: 0');

$users = $db->GetArray('SELECT *
	FROM users
	WHERE status in (1, 2) 
		and users.accountrole <> 'guest' order by letscode');

echo '"Letscode","Naam","Saldo"';
echo "\r\n";

foreach($users as $key => $value)
{
	$value["balance"] = $value["saldo"];
	echo "\"";
	echo $value["letscode"];
	echo "\",";
	echo "\"";
	echo $value["fullname"];
	echo "\",";
	echo "\"";
	echo $value["balance"];
	echo "\"";

	echo "\r\n";
}

