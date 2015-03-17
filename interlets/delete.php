<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET["id"];

if(empty($id)){
	header('Location: ' . $rootpath . 'overview.php');
	exit;
}

if(isset($_POST["zend"]))
{
	if($db->Execute('DELETE FROM letsgroups WHERE id = ' . $id))
	{
		$alert->success('Letsgroup verwijderd.');
		header('Location: ' . $rootpath . 'interlets/overview.php');
		exit;
	}

	$alert->error('Letsgroup niet verwijderd.');
}

include($rootpath."includes/inc_header.php");

echo "<h1>LETS groep verwijderen</h1>";

$groupname = $db->GetOne('SELECT groupname FROM letsgroups WHERE id = ' . $id);
echo "<div >";
echo "LETS Groep: " .$groupname;
echo "</div>";
echo "<p><font color='red'><strong>Ben je zeker dat deze groep";
echo " moet verwijderd worden?</strong></font></p>";
echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
echo "<input type='submit' value='Verwijderen' name='zend'>";
echo "</form></p>";
echo "</div>";
	
include($rootpath."includes/inc_footer.php");
