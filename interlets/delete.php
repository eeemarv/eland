<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$id = $_GET["id"];

if(empty($id)){
	header('Location: ' . $rootpath . 'overview.php');
	exit;
}

if(isset($_POST['zend']))
{
	if($db->delete('letsgroups', array('id' => $id)))
	{
		$alert->success('Letsgroep verwijderd.');
		header('Location: ' . $rootpath . 'interlets/overview.php');
		exit;
	}

	$alert->error('Letsgroep niet verwijderd.');
}
$groupname = $db->fetchColumn('SELECT groupname FROM letsgroups WHERE id = ?', array($id));

$h1 = 'Letsgroep verwijderen: ' . $groupname;
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo "<p><font color='red'><strong>Ben je zeker dat deze groep";
echo " moet verwijderd worden?</strong></font></p>";
echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
echo '<a href="' . $rootpath . 'interlets/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo "</form></p>";
echo "</div>";

echo '</div>';
echo '</div>';
	
include $rootpath . 'includes/inc_footer.php';
