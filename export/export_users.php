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
	$users = get_users();
	show_all_users($users);
}else{
	redirect_login($rootpath);
}

///////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=elas-users-".date("Y-m-d").".csv");
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

function get_users(){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "ORDER BY letscode";
        $list_users = $db->GetArray($query);
        return $list_users;
}

function show_all_users($users){
	echo '"letscode","cdate","comments","hobbies","name","postcode","login","mailinglist","password","accountrole","status","lastlogin","minlimit","fullname","admincomment","activeringsdatum"';
        echo "\r\n";
	foreach($users as $key => $value){
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
}
