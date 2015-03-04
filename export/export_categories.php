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

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$categories = get_categories();
	show_all_categories($categories);
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
        header("Content-disposition: attachment; filename=elas-categories-".date("Y-m-d").".csv");
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

function get_categories(){
	global $db;

        $query = "SELECT * FROM categories";

	$list_categories = $db->GetArray($query);
	return $list_categories;
}

function show_all_categories($categories){
	echo '"name","id_parent","description","cdate","fullname","leafnote"';
        echo "\r\n";
	foreach($categories as $key => $value){
		echo "\"";
                echo $value["name"];
		echo "\",";
		echo "\"";
		echo $value["id_parent"];
		echo "\",";
		echo "\"";
		echo $value["description"];
                echo "\",";
                echo "\"";
		echo $value["cdate"];
                echo "\",";
                echo "\"";
                echo $value["fullname"];
                echo "\",";
                echo "\"";
                echo $value["leafnote"];
		echo "\"";
                echo "\r\n";
        }
}

#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
