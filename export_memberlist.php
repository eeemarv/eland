<?php
ob_start();
$rootpath = "";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

show_ptitle();
$userrows = get_all_users($user_orderby);
show_all_users($userrows);

/////////////////

function show_ptitle(){
	header("Content-disposition: attachment; filename=marva-memberlist".date("Y-m-d").".csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: binary");
	header("Pragma: no-cache");
	header("Expires: 0");
}

function show_legend(){
echo "Status 1: OK<br>";
echo "Status 2: Uitstapper<br>";
echo "Status 3: Instapper";
echo "Status 4: Secretariaat";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
	$contactrows = $db->GetArray($query);
	return $contactrows;
}

function get_va($userid){
	global $db;
	$query = "SELECT count(*) FROM messages ";
	$query .= " WHERE id_user =".$userid;
	$resultrow = $db->GetRow($query);
	return $resultrow;
}

function get_all_users($user_orderby){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE status <> 0  ";
	$query .= "ORDER BY users.letscode ";

	$userrows = $db->GetArray($query);
	return $userrows;
}

function show_all_users($userrows){
	echo '"Status","Letscode","Naam","Tel","gsm","Postcode","Adres","Mail","Stand","fullname","lijn1","lijn2","VA"';
	echo "\r\n";
	foreach($userrows as $key => $value){
	 	echo '"';
		echo $value["status"];
		echo '","';
			//echo "status is 2";
		//}elseif($value["status"] == 3){
			//echo "status is 3";
		//}

		echo $value["letscode"];
		echo '","';
		echo $value["name"];
		echo '","';
		$userid = $value["id"];
		$contactrows = get_contacts($userid);
		        reset($contactrows);

			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 1){
					echo  $value2["value"];

				break;
				}
			}
		echo '","';
		        reset($contactrows);
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 2){
					echo $value2["value"];
					break;
				}
			}
		echo '","';
		echo $value["postcode"];
		echo '","';
		        reset($contactrows);
		$lijn1="";$lijn2="";
		foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 4){
					echo $value2["value"];
					list($lijn1,$lijn2)=split(",",$value2["value"]);
					break;
				}
		}
		echo '","';
		        reset($contactrows);

		foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3){
					echo $value2["value"];

					break;
				}
		}
		echo '","';

		$balance = $value["saldo"];
		echo $balance;

		echo '","';
		echo $value["fullname"];
		echo '","';
		echo $lijn1;
		echo '","';
		echo $lijn2;
		echo '","';
		$countva=get_va($userid);
		echo $countva[0];
		echo '"';
		echo "\r\n";

	}

}

?>
