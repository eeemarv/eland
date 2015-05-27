<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET['id'];

if(!isset($id))
{
	$alert->error('id niet bepaald.');
	header("Location: overview.php");
	exit;
}

if(isset($_POST['zend']))
{
	if ($db->Execute('DELETE FROM apikeys WHERE id=' .$id))
	{
		$alert->success('Apikey verwijderd.');
		header('Location: ' . $rootpath . 'apikeys/overview.php');
		exit;
	}
	$alert->error('Apikey niet verwijderd.');
}

$apikey = $db->GetRow('SELECT * FROM apikeys WHERE id = ' . $id);

include $rootpath . 'includes/inc_header.php';

echo '<h1><span class="label label-danger">Admin</span> Apikey verwijderen?</h1>';

echo '<form method="post" class="form-horizontal">';
echo '<dl>';
echo '<dt>Apikey</dt>';
echo '<dd>' . $apikey['apikey'] . '</dd>';
echo '<dt>Comment</dt>';
echo '<dd>' . $apikey['comment'] .  '</dd>';
echo '</dl>';
echo '<a href="' . $rootpath . 'apikeys/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo '</form>';

include $rootpath . 'includes/inc_footer.php';
