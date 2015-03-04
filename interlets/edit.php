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

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

$mode = $_GET["mode"];
$id = $_GET["id"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	show_form();
	if($mode == "edit"){
		//Load the current values
		loadvalues($id);
		writecontrol("mode", "edit");
	} else {
		writecontrol("mode", "new");
	}
	show_serveroutputdiv();
        show_closebutton();
}else{
	echo "<script type=\"text/javascript\">self.close();</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function loadvalues($id){
	$letsgroup = get_letsgroup($id);
	writecontrol("id", $letsgroup["id"]);
	writecontrol("groupname", $letsgroup["groupname"]);
	writecontrol("shortname", $letsgroup["shortname"]);
	writecontrol("prefix", $letsgroup["prefix"]);
    writecontrol("apimethod", $letsgroup["apimethod"]);
	writecontrol("remoteapikey", $letsgroup["remoteapikey"]);
	writecontrol("localletscode", $letsgroup["localletscode"]);
	writecontrol("myremoteletscode", $letsgroup["myremoteletscode"]);
	writecontrol("url", $letsgroup["url"]);
	writecontrol("elassoapurl", $letsgroup["elassoapurl"]);
	writecontrol("presharedkey", $letsgroup["presharedkey"]);
	//writecontrol("pubkey", $letsgroup["pubkey"]);
}

function writecontrol($key,$value){
	echo "<script type=\"text/javascript\">document.getElementById('" .$key ."').value = '" .$value ."';</script>";
}

function show_closebutton(){
        echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
        echo "<input type='button' id='close' value='Sluiten' onclick='self.close(); window.opener.location.reload();'>";
        echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle(){
	global $mode;
	echo "<h1>LETS groep ";
	if($mode == "new") {
		echo "toevoegen";
	} else {
		echo "wijzigen";
	}
	echo "</h1>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function show_form(){
	global $mode;
	echo "<script type='text/javascript' src='/js/postletsgroup.js'></script>";
	echo "<div class='border_b'><p>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('userform'));\" name='userform' id='userform'>";
	echo "<input type='hidden' name='mode' id='mode' size='4' value='new'>";
	echo "<input type='hidden' name='id' id='id' size='4'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";

	echo "<tr><td align='right' valign='top'>";
	echo "Groupnaam";
	echo "</td>\n<td valign='top'>";
	echo "<input type='text' name='groupname' id='groupname' size='30'>";
	echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
        echo "Korte naam:<br><small><i>(kleine letters zonder spaties)</i></small>";
	echo "</td>\n<td valign='top'>";
	echo "<input type='text' name='shortname' id='shortname' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
        echo "Prefix:<br><small><i>(kleine letters zonder spaties)</i></small>";
        echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='prefix' id='prefix' size='8'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
        echo "API Methode<br><small><i>(Type connectie naar de andere installatie)</i></small>";
	echo "</td>\n<td valign='top'>";
	echo "<select name='apimethod' id='apimethod'>";
	echo "<option value='elassoap'>eLAS naar eLAS (elassoap)</option>";
	echo "<option value='internal'>Intern (eigen installatie)</option>";
	echo "<option value='mail'>E-Mail</option>";
	echo "</select>";
	echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
        echo "Remote API key";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='remoteapikey' id='remoteapikey' size='45'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
        echo "Lokale LETS code<br><small><i>(De letscode waarmee de andere groep op deze installatie bekend is)</i></small>";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='localletscode' id='localletscode' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

        echo "<tr><td align='right' valign='top'>";
        echo "Remote LETS code<br><small><i>(De letscode waarmee deze groep bij de andere bekend is)</i></small>";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='myremoteletscode' id='myremoteletscode' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

        echo "<tr><td align='right' valign='top'>";
        echo "URL";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='url' id='url' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

        echo "<tr><td align='right' valign='top'>";
        echo "SOAP URL<br><small><i>(voor eLAS, de URL met /soap erachter)</i></small>";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='elassoapurl' id='elassoapurl' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

        echo "<tr><td align='right' valign='top'>";
        echo "Preshared key";
	echo "</td>\n<td valign='top'>";
        echo "<input type='text' name='presharedkey' id='presharedkey' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr>\n<td colspan='2' align='right' valign='top'>";
	echo "<input type='submit' name='zend' id='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n</table>\n\n";
	echo "</form>";

	//echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('userform'));\" name='activateform' id='activateform'>";
	//echo "<input type='submit' name='activate' id='activate' value='Activeren'>";
	//echo "</form>";
	//echo "</p></div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
