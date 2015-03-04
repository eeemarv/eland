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

$id = $_GET["id"];
if(empty($id)){
	echo "<script type=\"text/javascript\">self.close();</script>";
}

$msg = get_msg($id);

if($s_accountrole == "admin" || $msg["id_user"] == $s_id){
	show_ptitle();
	if(isset($_POST["zend"])){
		$cat_id = $msg["id_category"];
		delete_msg($id,$rootpath);
		echo "<script type=\"text/javascript\">self.close(); window.opener.location='/'</script>";
	}else{
		show_msg($msg);
		show_confirmation($msg);
		show_form($id);
	}
}else{
	echo "<script type=\"text/javascript\">self.close();</script>";
}
////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Vraag & Aanbod verwijderen</h1>";
}

function show_form($id){
	//echo "Message $id";
	echo "<div class='border_b' align='right'><p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'>";
	echo "</form></p>";
	echo "</div>";
}

function show_confirmation($msg){
	echo "<p><font color='red'><strong>Ben je zeker dat ";
	if($msg["msg_type"] == 0){
		echo "deze vraag";
	}elseif($msg["msg_type"] == 1){
		echo "dit aanbod";
	}
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_msg($id,$rootpath){
	global $db;
	global $baseurl;
	global $dirbase;
	// Delete all physical picture files first
	$pq = "SELECT * FROM msgpictures WHERE msgid = ".$id ;
	$pictures = $db->Execute($pq);
	foreach($pictures as $key => $value){
        	$target =  $rootpath ."sites/$dirbase/msgpictures/".$value["PictureFile"];
		//echo "unlinking $target";
		unlink($target);
	}

	$q1 = "DELETE FROM msgpictures WHERE msgid = ".$id ;
	$result = $db->Execute($q1);
	$q2 = "DELETE FROM messages WHERE id =".$id ;
	$result2 = $db->Execute($q2);
	if($result == TRUE && $result2 == TRUE){
		setstatus("V/A $id verwijderd", 0);
	} else {
		setstatus("V/A verwijderen mislukt", 1);
	}
}

function get_msg($id){
    global $db;
	$query = "SELECT *, ";
	$query .= " users.name AS uname, ";
	$query .= " messages.cdate AS date, ";
	$query .= " messages.validity AS valdate ";
	$query .= " FROM messages, users, categories ";
	$query .= " WHERE messages.id=" .$id;
	$query .= " AND messages.id_category = categories.id ";
	$query .= " AND messages.id_user = users.id ";
	$msg = $db->GetRow($query);
	return $msg;
}

function show_msg($msg){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top' nowrap><strong>V/A</strong></td>";
	echo "<td valign='top' nowrap><strong>Wat</strong></td>";
	echo "<td valign='top' nowrap><strong>Wie</strong></td>";
	echo "<td valign='top' nowrap><strong>Geldig tot</strong></td>";
	echo "<td valign='top' nowrap><strong>Categorie</strong></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td valign='top' nowrap>";
	 	if ($msg["msg_type"] == 0){
 		echo "V ";
	}elseif($msg["msg_type"] == 1){
		echo "A ";
	}
	echo "</td>";
	echo "<td valign='top'>";
	echo nl2br(htmlspecialchars($msg["content"],ENT_QUOTES));
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($msg["uname"],ENT_QUOTES)." (".trim($msg["letscode"]).")<br>";
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo $msg["valdate"];
	echo "</td>";
	echo "<td valign='top'>";
	echo htmlspecialchars($msg["fullname"],ENT_QUOTES);
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";
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
