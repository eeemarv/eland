<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");


if(isset($s_id) && ($s_accountrole == "admin")){
	showlinks($rootpath);
	show_ptitle1();
	$apikeys= get_apikeys();
	show_keys($apikeys);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////


function showlinks($rootpath){
	global $s_id;
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="add.php";
        echo "<li><a href='#' onclick=window.open('$myurl','addkey','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Apikey toevoegen</a></li>";
        echo "</div>";
        echo "</td></tr></table>";
}
	
function show_outputdiv(){
	echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type=\"text/javascript\">loadurl('rendergroups.php');</script>";
	echo "</div>";
}


function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle1(){
	echo "<h1>Overzicht API Keys</h1>";
}

function show_keys($apikeys){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "ID";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Apikey";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
    echo "Creatiedatum";
    echo "</strong></td>";
    echo "<td valign='top'><strong>";
	echo "Type";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Comment";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
        echo "Verwijderen";
        echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;
	foreach($apikeys as $key => $value){
		$rownumb=$rownumb+1;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        	echo " class='even_row'";
		}
		echo ">";

		echo "<td>" .$value['id'] ."</td>";
		echo "<td>" .$value['apikey'] ."</td>";
		echo "<td>" .$value['created'] ."</td>";
		echo "<td>" .$value['type'] ."</td>";
		echo "<td>" .$value['comment'] ."</td>";
		echo "<td> | ";
		$myurl = "delete.php?id=" .$value['id'];
		echo "<a href='#' onclick=window.open('$myurl','deletekey','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>";
		echo "Verwijderen |</a></td>";
	}
	echo "</tr>";
	echo "</table>";
		
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
