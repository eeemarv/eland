<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$contacts=get_contacts();
	show_all_contacts($contacts);
}else{
	redirect_login($rootpath);
}

////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=elas-contact-".date("Y-m-d").".csv");
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
}

function get_user($id){
        global $db;
        $query = "SELECT *";
        $query .= " FROM users ";
        $query .= " WHERE id='".$id."'";
        $user = $db->GetRow($query);
        return $user;
}

function get_contacts(){
	global $db;

        $query = "SELECT * FROM contact";

	$list_contacts = $db->GetArray($query);
	return $list_contacts;
}

function show_all_contacts($contacts){

	echo '"letscode","id_type_contact","comments","value","flag_public"';
        echo "\r\n";
	foreach($contacts as $key => $value){
		$user = get_user($value["id_user"]);
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

