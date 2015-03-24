<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$active_users = $db->GetArray(
	'SELECT letscode AS c,
		name as n,
		minlimit as l,
		saldo as b,
		adate as a,
		status as s
	FROM users
	WHERE status IN (1, 2)'
);

ob_clean();

header('Content-type: application/json');
echo json_encode($active_users);
