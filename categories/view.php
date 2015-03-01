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
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$cat = get_cat($id);
		show_ptitle();
		show_cat($cat);
	}else{
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
 	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Categorie bekijken</h1>";
}

function show_cat($cat){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>Hoofdcategorie</strong></td>";
	echo "<td valign='top'><strong>Naam</strong></td>";
	echo "</tr>";
	echo "<tr>";
	
	echo "<td valign='top' nowrap>";
	if ($cat["id_parent"] == 0){
		echo "Ja";
	}else{
		global $db;
		$query = "SELECT * FROM categories WHERE id=".$cat["id_parent"]." ";
		$parent = $db->GetRow($query);
		echo $parent["name"];
	}
	echo "</td>";
		
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($cat["name"],ENT_QUOTES);
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";
		
	echo "<div class='border_b'>";
	echo "| <a href='edit.php?id=".$cat["id"]."'>Aanpassen</a> |";
	echo "<a href='delete.php?id=".$cat["id"]."'>Verwijderen</a> |";
	echo "</div>";
	
}

function get_cat($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " cdate AS date ";
	$query .= " FROM categories  ";
	$query .= "WHERE id=".$id;
	$cat = $db->GetRow($query);
	return $cat;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

