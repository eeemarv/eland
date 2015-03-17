<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$messages=get_messages();
	show_all_messages($messages);
}else{
	redirect_login($rootpath);
}

////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=elas-messages-".date("Y-m-d").".csv");
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

function get_messages(){
	global $db;

        $query = "SELECT * FROM messages WHERE validity > " .time();

	$list_contacts = $db->GetArray($query);
	return $list_contacts;
}

function show_all_messages($messages){

	echo '"letscode","cdate","validity","id_category","content","msg_type"';
        echo "\r\n";
	foreach($messages as $key => $value){
		$user = get_user($value["id_user"]);
		echo "\"";
                echo $user["letscode"];
		echo "\",";
		echo "\"";
                echo $value["cdate"];
                echo "\",";
		echo "\"";
		echo $value["validity"];
		echo "\",";
		echo "\"";
		echo $value["id_category"];
                echo "\",";
                echo "\"";
		echo $value["content"];
                echo "\",";
                echo "\"";
                echo $value["msg_type"];
		echo "\"";
                echo "\r\n";
        }
}
