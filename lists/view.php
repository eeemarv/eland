<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailinglists.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
		$list = get_mailinglist($_GET["list"]);
		//var_dump($list);
		show_ptitle($list);
		echo "<table width='95%' border='1'>";
		echo "<tr>";
		echo "<td>";
		show_list($list);
		echo "</td>";
		echo "</tr>";
		echo "</table>";
		//show_serveroutputdiv();
		show_editlinks($_GET["list"]);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($list){
	echo "<h1>Mailing list " .$list["listname"] ."</h1>";
}

function show_editlinks($id){
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="edit.php?mode=edit&id=$id";
        echo "<li><a href='#' onclick=window.open('$myurl','group_edit','width=640,height=800,scrollbars=yes,toolbar=no,location=no,menubar=no')>Aanpassen</a></li>";
        $myurl="delete.php?id=$id";
        echo "<li><a href='#' onclick=window.open('$myurl','group_delete','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Verwijderen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_list($list){
	global $rootpath;
	echo "<div >";
	echo "<table width='95%' border='0'>";

	echo "<tr>";
	echo "<td>Lijstnaam</td>";
	echo "<td>" .$list["listname"] ."</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>Omschrijving</td>";
	echo "<td>" .$list["description"] ."</td>";
	echo "</tr>";
	
	echo "<tr>";
	echo "<td>Type</td>";
	echo "<td>" .$list["type"] ."</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>Onderwerp</td>";
	echo "<td>" .$list["topic"] ."</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>Autorisatie</td>";
	echo "<td>" .$list["auth"] ."</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>Moderatie</td>";
	echo "<td>" .$list["moderation"] ."</td>";
	echo "</tr>";
	
	echo "<tr>";
	echo "<td>Moderatiemail</td>";
	echo "<td>" .$list["moderatormail"] ."</td>";
	echo "</tr>";
	
	echo "<tr>";
	echo "<td>Ledenbron</td>";
	echo "<td>" .$list["subscribers"] ."</td>";
	echo "</tr>";	
				
	echo "</table>";
	echo "</div>";
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

