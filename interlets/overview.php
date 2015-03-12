<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

include($rootpath."includes/inc_header.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	showlinks($rootpath);
	show_ptitle1();
	show_groups();
	show_comment();
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
        $myurl="edit.php?mode=new";
        echo "<li><a href='#' onclick=window.open('$myurl','addgroup','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Groep toevoegen</a></li>";
	$myurl="renderqueue.php";
	echo "<li><a href='#' onclick=window.open('$myurl','showq','width=1200,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Interlets Queue</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_outputdiv(){
	echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type=\"text/javascript\">loadurl('rendergroups.php');</script>";
	echo "</div>";
}

function show_comment(){
	echo "<p><small><i>";
	echo "Belangrijk: er moet zeker een interletsrekening bestaan van het type internal om eLAS toe te laten met zichzelf te communiceren.  Deze moet een geldige SOAP URL en Apikey hebben.";
	echo "</i></small></p>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle1(){
	echo "<h1>Overzicht LETS groepen</h1>";
}

function show_groups(){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "ID";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Groepnaam";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "API";
	echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;
	$groups = getgroups();
	foreach($groups as $value){
		$rownumb++;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        	echo " class='even_row'";
		}
		echo ">";

		echo "<td>" .$value['id'] ."</td>";
		echo "<td><a href='view.php?id=" .$value['id'] ."'>" .$value['groupname'] ."</a></td>";
		echo "<td>" .$value['apimethod'] ."</td>";
	}
	echo "</tr>";
	echo "</table>";

}

function getgroups(){
	global $db;
        $query = "SELECT * FROM letsgroups";
        $groups = $db->GetArray($query);
        return $groups;
}

include($rootpath."includes/inc_footer.php");

