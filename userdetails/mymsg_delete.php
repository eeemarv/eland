<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

$id = $_GET["id"];
if(empty($id)){
	header("Location:  mymsg_overview.php");
	exit;
}

if(isset($_POST["zend"])){
	if ($db->Execute('DELETE FROM messages WHERE id = ' . $id . ' AND id_user = ' . $s_id))
	{
		$alert->success('Vraag/aanbod verwijderd.');
		redirect_overview();
		exit;
	}

	$alert->error('Vraag/aanbod verwijderen mislukt.');
}


include($rootpath."includes/inc_header.php");
echo "<h1>Mijn Vraag & Aanbod verwijderen</h1>";
$msg = get_msg($id);
show_msg($msg);
ask_confirmation($msg);

include($rootpath."includes/inc_footer.php");

////////////////////////

function show_form($id){
	echo "<div class='border_b'>";
	echo "<p><form action='mymsg_delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='Verwijderen' name='zend'";
	echo "</form></p></div>";
}

function ask_confirmation($msg){
	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat ";
	if($msg["msg_type"] == 0){
		echo "deze vraag";
	}elseif($msg["msg_type"] == 1){
		echo "dit aanbod";
	}
	echo " moet verwijderd worden?</strong></p></font></div>";
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
	echo "<td valign='top' nowrap><strong>Geldig tot</strong></td>";
	echo "<td valign='top' nowrap><strong>Categorie</strong></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td valign='top' nowrap>";
	 if ($msg["msg_type"] == 0){
	 	echo "V";
	}elseif($msg["msg_type"] == 1){
		echo "A";
	}
	echo "</td>";
	echo "<td valign='top' >";
	echo nl2br(htmlspecialchars($msg["content"],ENT_QUOTES));
	echo "</td>";

	echo "<td valign='top' >";
	echo $msg["valdate"];
	echo "</td>";

	echo "<td valign='top' >";
	echo htmlspecialchars($msg["fullname"],ENT_QUOTES);
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";

}
