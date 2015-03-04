<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

#include($rootpath."includes/inc_header.php");
#include($rootpath."includes/inc_nav.php");

include("inc_transperuser.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];
$user_prefix = $_GET["prefix"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle($user_userid,$user_datefrom,$user_dateto);
	$transactions = get_all_transactions($user_userid,$user_datefrom,$user_dateto,$user_prefix);
	show_all_transactions($transactions);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=marva-transactions-".date("Y-m-d").".csv");
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
        $query .= "WHERE (status = 1  ";
        $query .= "OR status =2 OR status = 3)  ";
        $query .= "AND users.accountrole <> 'guest' ";
        $query .= " order by letscode";
        $list_users = $db->GetArray($query);
        return $list_users;
}

function show_all_transactions($transactions){

        //echo '"id","Creatiedatum","Transactiedatum","Van","Aan","Bedrag","Dienst","Ingebracht door"';
	echo '"Datum","Van","Aan","Bedrag","Dienst"';
        echo "\r\n";
	foreach($transactions as $key => $value){
		echo "\"";
                echo $value["datum"];
		echo "\",";
		echo "\"";
                echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo "\",";
		echo "\"";
                echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo "\",";
		echo "\"";
                echo $value["amount"];
		echo "\",";
		echo "\"";
                echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "\"";

                echo "\r\n";
        }
}

#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
