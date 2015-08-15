<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$id = $_GET['id'];

if(!$id)
{
	$alert->warning('Geen id!');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

$ct = $db->GetRow('select * from type_contact tc');

if (in_array($ct['abbrev'], array('mail', 'tel', 'gsm', 'adr', 'web')))
{
	$alert->warning('Beschermd contact type.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

if ($db->GetOne('select id from contact where id_type_contact = ' . $id))
{
	$alert->warning('Er is ten minste één contact van dit contact type, dus kan het conact type niet verwijderd worden.');
	header('Location: ' . $rootpath . 'type_contact/overview.php');
	exit;
}

if(isset($_POST['zend']))
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
$fa = 'circle-o-notch';

include $rootpath . 'includes/inc_header.php';	

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';
echo '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
echo '<form method="post">';
echo '<a href="' . $rootpath . 'type_contact/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo '</form>';
echo '</div>';
echo '</div>';

include($rootpath."includes/inc_footer.php");	
