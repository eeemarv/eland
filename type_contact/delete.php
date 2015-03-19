<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!(isset($s_id) && ($s_accountrole == "admin"))){
	header("Location: ".$rootpath."login.php");
	exit;
}

$id = $_GET["id"];
if(empty($id)){
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$contacttype = get_contacttype($id);

if (in_array($contacttype['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

if(isset($_POST["zend"])){
	delete_contacttype($id);
	$alert->success('Contact type verwijderd.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

include($rootpath."includes/inc_header.php");	
echo "<h1>Contacttype verwijderen</h1>";
show_contacttype($contacttype);
ask_confirmation($contacttype);
show_form($id);
include($rootpath."includes/inc_footer.php");	


////////////////


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
