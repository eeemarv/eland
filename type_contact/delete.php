<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET['id'];

if(!$id)
{
	$alert->warning('Geen id!');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$ct = $db->GetRow('select tc.*, count(c.id)
	from type_contact tc, contact c
	where tc.id = ' . $id . '
		and c.id_type_contact = tc.id');

if (in_array($ct['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

if(isset($_POST["zend"]))
{
	if ($db->Execute('delete from type_contact where id = ' . $id))
	{
		$alert->success('Contact type verwijderd.');
	}
	else
	{
		$db->error('Fout bij het verwijderen.');
	}
	
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$h1 = 'Contact type verwijderen: ' . $ct['name'];

include $rootpath . 'includes/inc_header.php';	

echo '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
echo '<form method="post">';
echo '<a href="' . $rootpath . 'type_contact/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo '</form>';

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
