<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$id = $_GET["id"];

if(!$id){
	header('Location: overview.php');
}

if(isset($_POST["zend"])){
	if ($db->Execute("DELETE FROM categories WHERE id =".$id))
	{
		$alert->success('Categorie verwijderd.');
		header('Location: overview.php');
		exit;
	}

	$alert->error('Categorie niet verwijderd.');
}

$fullname = $db->GetOne('SELECT fullname FROM categories WHERE id = ' . $id);

echo '<h1>Categorie verwijderen : ' . $fullname . '</h1>';
echo "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
echo " moet verwijderd worden?</strong></font></p>";
echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
echo "<input type='submit' value='Verwijderen' name='zend'>";
echo "</form></p></div>";

include($rootpath."includes/inc_footer.php");
