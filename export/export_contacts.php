<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];


header("Content-disposition: attachment; filename=elas-contact-".date("Y-m-d").".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

$list_contacts = $db->GetArray("SELECT * FROM contact");
show_all_contacts($contacts);


////////////////////////

function show_all_contacts($contacts){

	echo '"letscode","id_type_contact","comments","value","flag_public"';
        echo "\r\n";
	foreach($contacts as $key => $value){
		$user = readuser($value["id_user"]);
		echo "\"";
                echo $user["letscode"];
		echo "\",";
		echo "\"";
		echo $value["id_type_contact"];
		echo "\",";
		echo "\"";
		echo $value["comments"];
                echo "\",";
                echo "\"";
		echo $value["value"];
                echo "\",";
                echo "\"";
                echo $value["flag_public"];
		echo "\"";
                echo "\r\n";
        }
}

