<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$id = $_GET['id'];

if(!$id)
{
	header('Location: ' . $rootpath . 'categories/overview.php');
}

if(isset($_POST['zend']))
{
	if ($db->delete('categories', array('id' => $id)))
	{
		$alert->success('Categorie verwijderd.');
		header('Location: overview.php');
		exit;
	}

	$alert->error('Categorie niet verwijderd.');
}

$fullname = $db->fetchColumn('SELECT fullname FROM categories WHERE id = ?', array($id));

$h1 = 'Categorie verwijderen : ' . $fullname;
$fa = 'clone';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
echo " moet verwijderd worden?</strong></font></p>";
echo '<form method="POST">';
echo '<a href="' . $rootpath . 'categories/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';

echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
