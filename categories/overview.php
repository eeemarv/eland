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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_addlink();
	show_ptitle();
	$cats = get_all_cats();
 	show_all_cats($cats);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_addlink(){
	echo "<div class='border_b'>| <a href='add.php'>Categorie toevoegen</a> |</div>";
}

function redirect_login($rootpath){
 	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Overzicht categorie&#235;n</h1>";
}

function show_all_cats($cats){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Categorie</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($cats as $key => $value){
				
		if ($value["id_parent"] == 0){
			echo "<tr class='even_row'>";
			echo "<td valign='top'><strong><a href='view.php?id=".$value["id"]."'>";
			echo htmlspecialchars($value["fullname"],ENT_QUOTES);
			echo "</a></strong></td>";
			echo "</tr>";
		}else{
			echo "<tr class='uneven_row'>";
			echo "<td valign='top'><a href='view.php?id=".$value["id"]."'>";
			echo htmlspecialchars($value["fullname"],ENT_QUOTES);
			echo "</a></td>";
			echo "<tr>";
		}
	}
	echo "</table></div>";
}


function get_all_cats(){
	global $db;
	$query = "SELECT *, cdate AS date FROM categories ";
	$query .= "ORDER BY fullname ";
	$cats = $db->GetArray($query);
	return $cats;
}


include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
