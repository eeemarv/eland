<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();global $_SESSION;
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");


//er is ingelogd	
if(isset($s_id)){
	//er is een id
	if (isset($_GET["id"])){
		$id = $_GET["id"];
	
		//haal msg
		$message = get_msg($id);
		
		show_ptitle();
		
		//toon msg
		show_msg($message, $s_accountrole);
	
	}else{
		//Geen id, naar overview
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Vraag & Aanbod</h1>";
}

function show_msg($message, $s_accountrole){

	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top' nowrap><strong>V/A</strong></td>";
	echo "<td valign='top' nowrap><strong>Wat</strong></td>";
	echo "<td valign='top' nowrap><strong>Wie</strong></td>";
	echo "<td valign='top' nowrap><strong>Categorie</strong></td>";
	echo "<td valign='top' nowrap><strong>Geldig tot</strong></td>";
	echo "</tr>";
	
	echo "<tr>";
	echo "<td valign='top' nowrap>";
	 	if ($message["msg_type"] == 0){
 		echo "V";
	}elseif($message["msg_type"] == 1){
		echo "A";
	} 
	echo "</td>";
	echo "<td valign='top'>";
	echo nl2br(htmlspecialchars($message["content"],ENT_QUOTES));
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($message["username"],ENT_QUOTES)." (".trim($message["letscode"]).")<br>";
	echo "</td>";
	echo "<td valign='top'>";
	echo htmlspecialchars($message["fullname"],ENT_QUOTES);
	echo "</td>";
	echo "<td valign='top' nowrap>";
	echo $message["valdate"];
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";
	
	
	
}

function get_msg($id){
	global $db;
	$query = "SELECT *, ";
	$query .= "messages.id AS msgid, ";
	$query .= " users.id AS userid, ";
	$query .= " categories.id AS catid, ";
	$query .= " messages.cdate AS date, ";
	$query .= " messages.validity AS valdate, ";
	$query .= "users.name AS username, ";
	$query .= "categories.name AS catname ";
	$query .= " FROM messages, users, categories ";
	$query .= " WHERE messages.id=".$id;
	$query .= " AND messages.id_user = users.id ";
	$query .= " AND messages.id_category = categories.id";
	$message = $db->GetRow($query);
	return $message;
}

function redirect_overview(){
	header("Location: overview.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

