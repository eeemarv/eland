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

$mode = $_GET["mode"];
$nid = $_GET["id"];

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

if(isset($s_id)){
	show_ptitle($mode);
	show_form();
	if($mode == "edit"){
		//Load the current values
		loadvalues($nid);
	} else {
		writecontrol("mode", "new");
		//writecontrol("id", );
	}

	show_serveroutputdiv();
	show_closebutton();
} else {
	echo "<script type='text/javascript'>self.close;</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_closebutton(){
        echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
	echo "<input type='button' id='close' value='Sluiten' onclick='self.close(); window.opener.location.reload();'>";

        echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle($mode){
	if($mode == "new"){
		echo "<h1>Nieuwsbericht toevoegen</h1>";
	} else {
		echo "<h1>Nieuwsbericht aanpassen</h1>";
	}
}

function loadvalues($nid){
	$newsitem = get_newsitem($nid);
	writecontrol("mode", "edit");
	writecontrol("id", $nid);
	writecontrol("itemdate", $newsitem["idate"]);
	writecontrol("location", $newsitem["location"]);
	writecontrol("headline", $newsitem["headline"]);
	writecontrol("newsitem", $newsitem["newsitem"]);
	writecontrol("sticky", $newsitem["sticky"]);
	//writecontrol("id" , $msg["id"]);
}

function writecontrol($key,$value){
        $value = str_replace("\n", '\n', $value);
        $value = str_replace('"',"'",$value);
        echo "<script type=\"text/javascript\">document.getElementById('" .$key ."').value = \"" .$value ."\";</script>";
}

function get_newsitem($id){
    global $db;
                $query = "SELECT * , ";
                $query .= " itemdate AS idate ";

                $query .= " FROM news WHERE id=".$id;
                $newsitem = $db->GetRow($query);
                return $newsitem;
}

function show_form(){
	echo "<div id='newsformdiv' class='border_b'><p>";
	echo "<script type='text/javascript' src='/js/postnews.js'></script>";
	echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<form action=\"javascript:showloader('serveroutput');getnews(document.getElementById('newsform'));\" name='newsform' id='newsform'>";
	echo "<input type='hidden' name='mode' id='mode'>";
	echo "<input type='hidden' name='id' id='id'>";
	echo "<tr>\n<td width='10%' valign='top' align='right'>Agendadatum: <i>wanneer gaat dit door?</i></td>\n<td valign='top' >";
	echo "<input type='text' name='itemdate' id='itemdate' size='50' ";
	echo  "value ='".date("Y-m-d")."'>";
	echo "</td>\n</tr>\n\n";
	echo "<tr>\n<td width='10%' valign='top' align='right'>Locatie</td><td>";
	echo "<input type='text' name='location' id='location' size='50'>";

	echo "</td></tr>\n\n<tr>\n<td></td>\n<td>";

	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Titel </td>\n<td>";
	echo "<input type='text' name='headline' id='headline' size='50'>";
	echo "</td></tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Nieuwsbericht </td>\n";
	echo "<td>";
	echo "<textarea name='newsitem' id='newsitem' cols='60' rows='15' >";
	echo "</textarea></td>\n</tr>\n\n<tr><td></td>\n<td>";

	echo "</td>\n</tr>\n\n";
	echo "<tr><td>Niet vervallen</td><td><input type=checkbox name='sticky' id='sticky'></td>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' name='zend' id='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n</table>\n\n";
	echo "</form>";
	echo "</p></div>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
