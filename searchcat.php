<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

include($rootpath."includes/inc_header.php");
echo "<h1>Vraag & Aanbod</h1>";
show_searchform($q,$s_user_postcode);
// show_outputdiv();
if($s_accountrole != 'guest'){
	echo "<h1>Andere (interlets) groepen raadplegen</h1>";
	show_interletsgroups();
}
include($rootpath."includes/inc_footer.php");

///////////////////

function show_searchform($q,$s_user_postcode){
	global $rootpath;
	echo "<form method='get' action='$rootpath/messages/search.php'>";
	echo "<input type='text' name='q' size='40' ";
	if (!empty($q)){
		echo " value=".$q;
	}
	echo ">";
	echo "<input type='submit' name='zend' value='Zoeken'>";
	echo "<br><small><i>Een leeg zoekveld geeft ALLE V/A als resultaat terug</i></small>";
/*	if(!empty($s_user_postcode) && filter_var($s_user_postcode, FILTER_VALIDATE_INT)) {
		echo "<br><small><i>Maximum afstand rond je gemeente : <input type='text' size='1' name='distance'> km.</i></small>";
	} else {
		echo "<br><small><i>Geef uw postcode in bij 'Mijn gegevens' om op max. afstand te kunnen zoeken</i></small>";
	} */
	echo "</form>";
}

function show_outputdiv(){
        echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
        echo "<script type=\"text/javascript\">loadurl('rendersearchcat.php')</script>";
        echo "</div>";
}

function show_interletsgroups(){
	global $db;
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";
	$query = "SELECT * FROM letsgroups WHERE apimethod <> 'internal'";
	$letsgroups = $db->Execute($query);
	foreach($letsgroups as $key => $value){
		echo "<tr><td nowrap>";
		//a href='#' onclick=window.open('$myurl','addgroup','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Groep toevoegen</a>
		//echo "<a href='" .$value["url"] ."'>" .$value["groupname"] ."</a>";a
		echo "<a href='#' onclick=window.open('interlets/redirect.php?letsgroup=" .$value["id"] ."','interlets','location=no,menubar=no,scrollbars=yes')>" .$value["groupname"] ."</a>";
		echo "</td></tr>";
	}

	echo "</table>";

}


