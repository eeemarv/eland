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
		#$list = get_mailinglist($_GET["list"]);
		show_ptitle();
		show_sendform();
}else{
	redirect_login($rootpath);
}

echo "<script type='text/javascript' src='$rootpath/js/moomlmsg.js'></script>";
echo "<script type='text/javascript' src='$rootpath/contrib/ckeditor/ckeditor.js'></script>";

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($list){
	echo "<h1>Bericht verzenden</h1>";
}

function show_sendform() {
	$lists = get_mailinglists();
	
    echo "<div id='msgformdiv'>";
    echo "<form action='". $rootpath ."/resources/mailinglist/message/new' id='msgform' method='post'>";
    echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
    
    // Topic
    echo "<tr><td valign='top' align='right'>Aan lijst</td>";
    echo "<td valign='top'>";
    echo "<select name='list'>\n";
    foreach ($lists as $value){
		echo "<option value='".$value["listname"]."' >" .$value["listname"] ."</option>\n";
	}
    echo "</select>";
    echo "</td>";
    echo "</tr>";
    
   
    // Moderatormail
    echo "<tr><td valign='top' align='right'>Onderwerp</td>";
    echo "<td valign='top'>";
    echo "<input  type='text' id='msgsubject' name='msgsubject' size='80'>";
    echo "</td>";
    echo "</tr>";

    echo "<tr><td valign='top' align='right'>Body</td>";
    echo "<td valign='top'>";
    echo "<textarea class='ckeditor' id='msgbody' name='msgbody' cols='80' rows='15'></textarea>";
    echo "</td>";
    echo "</tr>";
    
    echo "<tr><td colspan='2' align='right'>";
    echo "<input type='submit' id='zend' value='Verzenden' name='zend'>";
    echo "</td><td>&nbsp;</td></tr>";
    echo "</table>";
    echo "</form>";
    echo "</div>";
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

