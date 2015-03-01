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
	$id = $_GET["id"];
	if(empty($id)){
		redirect_overview();
	}else{
		show_ptitle();
		if(isset($_POST["zend"])){
			delete_cat($id);
			redirect_overview();
		}else{
			$cat = get_cat($id);
			show_cat($cat);
			ask_confirmation($cat);
			show_form($id);
		}
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
	echo "<h1>Categorie verwijderen</h1>";
}

function show_form($id){
	echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'>";
	echo "</form></p></div>";
}

function ask_confirmation($cat){
	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_cat($id){
	global $db;
	$query = "DELETE FROM categories WHERE id =".$id ;
	$result = $db->Execute($query);
}

function get_cat($id){
	global $db;
	$query = "SELECT *, cdate AS date FROM categories WHERE id=" .$id;
	$cat = $db->GetRow($query);
	return $cat;
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
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
