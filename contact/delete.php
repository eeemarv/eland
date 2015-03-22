<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$id = $_GET["id"];
if(empty($id)){
	redirect_overview($contact);
}else{
	show_ptitle();
	if(isset($_POST["zend"])){
		delete_contact($id);
		redirect_overview();
	}else{
		$contact = get_contact($id);
		show_contact($contact);
		ask_confirmation($contact);
		show_form($id);
	}
}

function show_ptitle(){
	echo "<h1>Contact verwijderen</h1>";
}

function show_form($id){
	echo "<div class = 'border_b'>";
	echo "<p><form action='delete.php?id=".$id."' method='POST'>";
	echo "<input type='submit' value='OK' name='zend'>";
	echo "</form></p>";
	echo "</div>";
}

function ask_confirmation($contact){
	echo "<p><font color='#F56DB5'><strong>Ben je zeker dat dit contact";
	echo " moet verwijderd worden?</strong></font></p>";
}

function delete_contact($id){
	 global $db;
	$query = "DELETE FROM contact WHERE id =".$id ;
	$result = $db->Execute($query);
}

function get_contact($id){
 	global $db;
	$query = "SELECT *, ";
	$query .= " type_contact.name AS tcname, ";
	$query .= " users.name AS uname, ";
	$query .= " contact.comments AS ccomments ";
	$query .= "FROM contact, type_contact, users  ";
	$query .= " WHERE contact.id=" .$id;
	$query .= " AND contact.id_user = users.id ";
	$query .= " AND contact.id_type_contact = type_contact.id";
	$contact = $db->GetRow($query);
	return $contact;
}

function show_contact($contact){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top' nowrap><strong>Type</strong></td>";
	echo "<td valign='top' nowrap><strong>Gebruiker</strong></td>";
	echo "<td valign='top' nowrap><strong>Waarde</strong></td>";
	echo "<td valign='top' nowrap><strong>Commentaar</strong></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["tcname"],ENT_QUOTES);
	echo "</td>";
	echo  "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["uname"],ENT_QUOTES)." (".$contact["letscode"].")";
	echo "</td>";
	echo  "<td valign='top' nowrap>";
	echo htmlspecialchars($contact["value"],ENT_QUOTES);
	echo "</td>";
	echo  "<td valign='top'>";
	echo htmlspecialchars($contact["ccomments"],ENT_QUOTES);
	echo "</td>";
	echo "</tr>";
	echo "</table></div>";
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_footer.php");
