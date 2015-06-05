<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$cid = $_GET["cid"];
$uid = $_GET["uid"];

if ($_POST['zend'])
{
	if ($db->Execute("DELETE FROM contact WHERE id =".$cid))
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

$contact = $db->GetRow('SELECT tc.abbrev, c.value, c.comments, c.flag_public, u.name, u.letscode
	FROM type_contact tc, contact c, users u
	WHERE c.id_type_contact = tc.id
		AND c.id_user = u.id
		AND c.id = ' . $cid);

$h1 = 'Contact verwijderen?';

include $rootpath . 'includes/inc_header.php';


echo '<p>Type: ' . $contact['abbrev'] . '</p>';
echo '<p>Waarde: ' . $contact['value'] . '</p>';
echo '<p>Commentaar: ' . $contact['comments'] . '</p>';
echo '<p>Publiek: ' . (($contact['flag_public']) ? 'ja' : 'nee') . '</p>';
echo '<p>Gebruiker: ' . $contact['name'] . ' ( ' . $contact['letscode'] . ' )</p>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post"><input type="submit" value="Verwijder" name="zend"></form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

