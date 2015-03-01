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

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");


if(isset($s_id) && ($s_accountrole == "admin")){
	$id = $_GET["id"];
	if(empty($id)){
		echo "<script type=\"text/javascript\">self.close();</script>";
	}else{
		show_ptitle();
		if(isset($_POST["zend"])){
			delete_newsitem($id);
			echo "<script type=\"text/javascript\">self.close(); window.opener.location='/'</script>";
		}else{
			$newsitem = get_newsitem($id);
			show_newsitem($newsitem);
			ask_confirmation($newsitem);
			show_form($id);
		}
	}
} else {
	echo "<script type=\"text/javascript\">self.close(); window.opener.location='/'</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Nieuwsbericht verwijderen</h1>";
}

function show_form($id){
	echo "<div align='right'><p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'>";
	echo "</form></p>";
	echo "</div>";
}

function ask_confirmation($newsitem){
	echo "<p><font color='red'><strong>Ben je zeker dat dit nieuwsbericht";
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_newsitem($id){
    global $db;
	$query = "DELETE FROM news WHERE id =".$id ;
	$result = $db->Execute($query);
}

function get_newsitem($id){
    global $db;
	$query = "SELECT *, ";
	$query .= " news.cdate AS date, ";
	$query .= " news.itemdate AS idate ";
	$query .= " FROM news, users ";
	$query .= " WHERE news.id=" .$id;
	$query .= " AND news.id_user = users.id";
	$newsitem = $db->GetRow($query);
	return $newsitem;
}

function show_newsitem($newsitem){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	//echo "<td valign='top'><strong>Toegevoegd op</strong></td>";
	echo "<td valign='top'><strong>Agendadatum</strong></td>";
	echo "<td valign='top'><strong>Nieuwsbericht</strong></td>";
	echo "</tr>";
	echo "<tr>";
	//echo "<td valign='top' nowrap>";
	//echo $newsitem["date"];
	//echo "</td>";
	echo "<td valign='top' nowrap>";
		if(trim($newsitem["idate"]) != "00/00/00"){ 
			echo $newsitem["idate"];
	}
	echo "</td>";
	echo "<td><strong>" .htmlspecialchars($newsitem["headline"],ENT_QUOTES)."</strong><br>";
	echo nl2br(htmlspecialchars($newsitem["newsitem"],ENT_QUOTES))."<br><br>";
	echo htmlspecialchars($newsitem["name"],ENT_QUOTES)." (".trim($newsitem["letscode"]).")";
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
