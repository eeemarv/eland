<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){

	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$group = get_letsgroup($id);
		show_ptitle($group['groupname']);
		echo "<table width='95%' border='1'>";
		echo "<tr>";
		echo "<td>";
		show_group($group);
		echo "</td>";

		echo "<td valign='top' width='300'>";
		show_status($id);
                echo "</td>";

		echo "</tr>";
		echo "</table>";
		show_legend();
		//show_serveroutputdiv();
		show_editlinks($id);
	}else{
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle($groupname){
	echo "<h1>$groupname</h1>";
}

function show_legend(){
	echo "<p><small><i>";
	echo "* API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail";
	echo "<br>* De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten";
	echo "<br>* Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan";
	echo "<br>* Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn";
        echo "<br>* URL is de weblocatie van de andere installatie";
        echo "<br>* SOAP URL is de locatie voor de communicatie tussen eLAS en het andere systeem, voor een andere eLAS is dat de URL met /soap erachter";
        echo "<br>* Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant";
	echo "</i></small></p>";
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

function show_status($id){
	echo "<script type='text/javascript' src='/js/soapstatus.js'></script>";
	echo "<table width='100%' border='0'>";

        echo "<tr>";
        echo "<td bgcolor='grey'>eLAS Soap status</td>";
	echo "</tr>";
        echo "<tr>";
        echo "<td><i><div id='statusdiv'>";
	echo "<script type='text/javascript'>showsmallloader('statusdiv')</script>";
	echo "</div></i>";
	echo "</td>";
	echo "</tr>";

	echo "</table>";
	echo "<script type='text/javascript'>soapstatus($id)</script>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_group($group){
	global $rootpath;
	echo "<div >";
	echo "<table width='95%' border='0'>";

	echo "<tr>";
	echo "<td>Groepnaam</td>";
	echo "<td>" .$group["groupname"] ."</td>";
	echo "</tr>";

	echo "<tr>";
        echo "<td>Korte naam</td>";
        echo "<td>" .$group["shortname"] ."</td>";
        echo "</tr>";

	echo "<tr>";
        echo "<td>Prefix</td>";
        echo "<td>" .$group["prefix"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>API methode</td>";
        echo "<td>" .$group["apimethod"] ."</td>";
        echo "</tr>";

	echo "<tr>";
        echo "<td>API key</td>";
        echo "<td>" .$group["remoteapikey"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>Lokale LETS code</td>";
        echo "<td>" .$group["localletscode"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>Remote LETS code</td>";
        echo "<td>" .$group["myremoteletscode"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>URL</td>";
        echo "<td>" .$group["url"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>SOAP URL</td>";
        echo "<td>" .$group["elassoapurl"] ."</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>Preshared Key</td>";
        echo "<td>" .$group["presharedkey"]."</td>";
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
