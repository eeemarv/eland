<?php

$rootpath = '../';
$role = 'admin';
require_once($rootpath.'includes/inc_default.php');

header('Content-disposition: attachment; filename=elas-users-'.date('Y-m-d').'.csv');
header('Content-Type: application/force-download');
header('Content-Transfer-Encoding: binary');
header('Pragma: no-cache');
header('Expires: 0');

$users = $db->fetchAll('SELECT *
	FROM users 
	ORDER BY letscode');

echo '"letscode","cdate","comments","hobbies","name","postcode","login","mailinglist","password","accountrole","status","lastlogin","minlimit","maxlimit","fullname","admincomment","activeringsdatum"';
echo "\r\n";

foreach($users as $key => $value)
{
	echo "\"";
	echo $value["letscode"];
	echo "\",";
	echo "\"";
	echo $value["cdate"];
	echo "\",";
	echo "\"";
	echo $value["comments"];
	echo "\",";
	echo "\"";
	echo $value["hobbies"];
	echo "\",";
	echo "\"";
	echo $value["name"];
	echo "\",";
	echo "\"";
	echo $value["postcode"];
	echo "\",";
	echo "\"";
	echo $value["login"];
	echo "\",";
	echo "\"";
	echo $value["mailinglist"];
	echo "\",";
	echo "\"";
	echo $value["password"];
	echo "\",";
	echo "\"";
	echo $value["accountrole"];
	echo "\",";
	echo "\"";
	echo $value["status"];
	echo "\",";
	echo "\"";
	echo $value["lastlogin"];
	echo "\",";
	echo "\"";
	echo $value["minlimit"];
	echo '", "';
	echo $value['maxlimit'];
	echo "\",";
	echo "\"";
	echo $value["fullname"];
	echo "\",";
	echo "\"";
	echo $value["admincomment"];
	echo "\",";
	echo "\"";
	echo $value["adate"];
	echo "\"";
	echo "\r\n";
}
