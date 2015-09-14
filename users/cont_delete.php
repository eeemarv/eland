<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

$cid = $_GET["cid"];
$uid = $_GET["uid"];

if ($_POST['zend'])
{
	if ($db->delete('contact', array('id' => $cid)))
	{
		$alert->success('Contact verwijderd.');
	}
	else
	{
		$alert->error('Contact niet verwijderd.');
	}
	header("Location: view.php?id=$uid");
	exit;
}

$contact = $db->fetchAssoc('SELECT tc.abbrev, c.value, c.comments, c.flag_public, u.fullname, u.letscode
	FROM type_contact tc, contact c, users u
	WHERE c.id_type_contact = tc.id
		AND c.id_user = u.id
		AND c.id = ?', array($cid));

$h1 = 'Contact verwijderen?';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<p>Gebruiker: ' . $contact['letscode'] . ' ' . $contact['fullname'] . '</p>';
echo '<p>Type: ' . $contact['abbrev'] . '</p>';
echo '<p>Waarde: ' . $contact['value'] . '</p>';
echo '<p>Commentaar: ' . $contact['comments'] . '</p>';
echo '<p>Publiek: ' . (($contact['flag_public']) ? 'ja' : 'nee') . '</p>';

echo '<form method="post" class="form-horizontal">';

echo '<a href="' . $rootpath . 'users/view.php?id=' . $uid . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

