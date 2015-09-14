<?php
ob_start();
$rootpath = "";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

show_ptitle();
$messagerows = get_all_msgs();
show_all_msgs($messagerows);

///////////

function show_all_msgs($messagerows){

	echo '"id","V/A","Wat","Wie","Letscode","Categorie","Gecreeerd op","Aangepast op","Geldig tot"';
	echo "\r\n";
foreach($messagerows as $key => $value){

		echo '"';
		echo $value["id"];
		echo '","';
		if($value["msg_type"]==0)
		{
			echo "V";
		}
		else if ($value["msg_type"]==1)
		{
			echo "A";
		}
		echo '","';
		echo $value["content"];
		echo '","';
		echo $value["username"];
		echo '","';
		echo $value["letscode"];
		echo '","';
		echo $value["fullname"];
		echo '","';
		echo $value["date"];
		echo '","';
		echo $value["moddate"];
echo '","';
		echo $value["valdate"];
		echo '"';
		echo "\r\n";
	}
}

function get_all_msgs(){
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS msgid, ";
	$query .= " users.id AS userid, ";
	$query .= " categories.id AS catid, ";
	$query .= " categories.fullname AS catname, ";
	$query .= " users.name AS username, ";
	$query .= " users.letscode AS letscode, ";
	$query .= " messages.cdate AS date, ";
	$query .= " messages.mdate AS moddate, ";
	$query .= " messages.validity AS valdate ";
	$query .= " FROM messages, users, categories ";
	$query .= "  WHERE messages.id_user = users.id ";
	$query .= " AND messages.id_category = categories.id";
	$query .= " AND validity > ?";
	//print $query;
	$messagerows = $db->fetchAll($query, array(date("Y-m-d")));
	return $messagerows;
}

function show_ptitle(){
#	echo "<h1>Transacties ";
#	echo date("d-m-Y");
#	echo " </h1>";
	header("Content-disposition: attachment; filename=marva-messages".date("Y-m-d").".csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Pragma: no-cache");
	header("Expires: 0");
}

?>
