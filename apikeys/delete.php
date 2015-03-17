<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET["id"];
if(!isset($id)){
	header("Location: overview.php");
	exit;
}

if(isset($_POST["zend"])){
	if ($db->Execute("DELETE FROM apikeys WHERE id=" .$id))
	{
		$alert->success('Apikey verwijderd.');
		header('Location: ' . $rootpath . 'apikeys/overview.php');
		exit;
	}
	$alert->error('Apikey niet verwijderd.');
}

$apikey = $db->GetRow('SELECT * FROM apikeys WHERE id = ' . $id);

include($rootpath."includes/inc_header.php");
echo "<h1>Apikey verwijderen</h1>";
echo '<p>' . $apikey['apikey'] . '</p>';
echo '<p>' . $apikey['comment'] . '</p>';
echo "<form action='delete.php?id=".$id ."' method='POST'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr>\n";
echo "<td>Apikey verwijderen? <input type='submit' value='Verwijderen' name='zend'></td>\n";
echo "</tr>\n\n</table>";
echo "</form>";

include($rootpath."includes/inc_footer.php");
