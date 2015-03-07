<?php
ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($_POST["search"])){
	$searchstring = $_POST["search"];
} else {
	$searchstring = "";
}

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();

	show_searchform($searchstring);

	//FIXME Add check for file age, fetch from logserver if stale
	$file = "$rootpath/sites/$baseurl/json/eventlog.json";
	$now = time();
	$filetime = filemtime($file);
	if(($now - $filetime) >= 120){
		get_elaslog();
	}

	$file = "$rootpath/sites/$baseurl/json/eventlog.json";
	$mylogs=json_decode(file_get_contents($file), true);

	show_logs($mylogs['logs'], $searchstring);
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
	echo "<h1>Event log</h1>";
}

function show_searchform($searchstring) {
	    echo "<div id='searcformdiv'>";
        echo "<form action='eventlog.php' id='searchform' method='post'>";
        echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
        echo "<tr><td valign='top' align='right'>Zoekterm</td>";
        echo "<td valign='top'>";
        echo "<input  type='text' id='search' name='search' value='" .$searchstring ."' size='30'>";
        echo "</td>";
        echo "</tr>";
        echo "<tr><td colspan='2' align='right'>";
        echo "<input type='submit' id='zend' value='Zoeken' name='zend'>";
        echo "</td><td>&nbsp;</td></tr>";
        echo "</table>";
        echo "</form>";
        echo "</div>";
}

function show_logs($logrows,$searchstring){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
	echo "<tr class='header'>\n";
	echo "<td>ID</td>\n";
	echo "<td>Timestamp</td>\n";
	echo "<td>Type</td>\n";
	echo "<td>Event</td>\n";
	echo "<td>IP address</a></td>\n";
	echo "</tr>";

	foreach($logrows as $key => $value){
		$printme = 0;
		$searchstring = strtolower($searchstring);
		if($searchstring == "") {
			$printme = 1;
		}

		if(preg_match("/$searchstring/", strtolower($value[3]))) {
			$printme = 1;
		}

		if($printme == 1) {
			echo "<tr>";
			echo "<td>" .$value[0] ."</td>";
			echo "<td nowrap>" .$value[1] ."</td>";
			echo "<td>" .$value[2] ."</td>";
			echo "<td>" .$value[3] ."</td>";
			echo "<td>" .$value[4] ."</td>";
			echo "</tr>";
		}
	}

	echo "</table></div>";

	//echo "<p><a href='export/export_eventlog.php'>CSV export</a></p>
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
