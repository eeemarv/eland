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
		redirect_overview($contacttype);
	}else{
		show_ptitle();
		if(isset($_POST["zend"])){
			delete_contacttype($id);
			redirect_overview();
		}else{
			$contacttype = get_contacttype($id);
			show_contacttype($contacttype);
			ask_confirmation($contacttype);
			show_form($id);
		}
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

function show_ptitle(){
	echo "<h1>Contacttype verwijderen</h1>";
}

function show_form($id){
	echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'>";
	echo "</form></p></div>";
	
}

function ask_confirmation($contacttype){
	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat dit contacttype";
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_contacttype($id){
    global $db;
	$query = "DELETE FROM type_contact WHERE id =".$id ;
	$result = $db->Execute($query);
}

function get_contacttype($id){
    global $db;
	$query = "SELECT * FROM type_contact WHERE id=" .$id;
	$contacttype = $db->GetRow($query);
	return $contacttype;
}

function show_contacttype($contacttype){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>Naam</strong></td>";
	echo "<td valign='top'><strong>Afkorting</strong></td>";
	echo "</tr>";
	
	echo "<tr>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contacttype["name"],ENT_QUOTES);
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contacttype["abbrev"],ENT_QUOTES);
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
