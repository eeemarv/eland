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

if(isset($s_id) && ($s_accountrole == "admin")){
	$id = $_GET["id"];
	if(empty($id)){
		echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
	}else{
		show_ptitle();
		if(isset($_POST["zend"])){
			$group = get_letsgroup($id);
			delete_group($id);
			echo "<script type=\"text/javascript\">self.close(); window.opener.location='/'</script>";
		}else{
			$group = get_letsgroup($id);
			show_group($group);
			show_confirmation();
			show_form($id);
		}
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>LETS groep verwijderen</h1>";
}

function show_form($id){
	echo "<div class='border_b' align='right'><p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'>";
	echo "</form></p>";
	echo "</div>";
}

function show_confirmation(){
	echo "<p><font color='red'><strong>Ben je zeker dat deze groep";
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_group($id){
	global $db;
	$query = "DELETE FROM letsgroups WHERE id =".$id ;
	$result = $db->Execute($query);
	if($result == TRUE){
		setstatus("letsgroup $id verwijderd", 0);
	} else {
		setstatus("letsgroup $id verwijderen mislukt", 1);
	}
}
	
function show_group($group){
	echo "<div >";
	echo "LETS Groep: " .$group["groupname"];
	echo "</div>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
