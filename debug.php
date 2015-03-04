<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

//include($rootpath."includes/inc_header.php");
//include($rootpath."includes/inc_nav.php");

show_ptitle();
show_body();

//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Over eLAS</h1>";
}

function show_body(){
	echo "Remote addr: " . $_SERVER['REMOTE_ADDR']."<br/>";
	echo "X Forward: " . $_SERVER['HTTP_X_FORWARDED_FOR']."<br/>";
	echo "Clien IP: " . $_SERVER['HTTP_CLIENT_IP']."<br/>";

	echo var_dump($_SERVER);

}

//include($rootpath."includes/inc_sidebar.php");
//include($rootpath."includes/inc_footer.php");
?>
