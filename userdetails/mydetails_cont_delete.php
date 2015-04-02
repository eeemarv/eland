<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($_GET['id']))
{
	$alert->error('Geen id');
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
	exit;
}

$id = $_GET["id"];

if (!validate_request($id))
{
	$alert->error('De instellingen van eLAS laten je niet toe deze informatie te verwijderen. Als je niet wil dat andere leden deze gegevens zien kan je de optie \'publiek\' uitschakelen.');
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
	exit;
}


if ($_POST['zend'])
{
	if ($db->Execute("DELETE FROM contact WHERE id =".$id))
	{
		$alert->success('Contact verwijderd.');
	}
	else
	{
		$alert->error('Contact niet verwijderd.');
	}
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
	exit;
}

$contact = $db->GetRow('SELECT tc.abbrev, c.value, c.comments, c.flag_public, u.name, u.letscode
	FROM type_contact tc, contact c, users u
	WHERE c.id_type_contact = tc.id
		AND c.id_user = u.id
		AND c.id = ' . $id);

include($rootpath."includes/inc_header.php");

echo '<h1>Contact verwijderen?</h1>';
echo '<p>Type: ' . $contact['abbrev'] . '</p>';
echo '<p>Waarde: ' . $contact['value'] . '</p>';
echo '<p>Commentaar: ' . $contact['comments'] . '</p>';
echo '<p>Publiek: ' . (($contact['flag_public']) ? 'ja' : 'nee') . '</p>';
echo '<p>Gebruiker: ' . $contact['name'] . ' ( ' . $contact['letscode'] . ' )</p>';
echo '<form method="post"><input type="submit" value="Verwijder" name="zend"></form>';

include($rootpath."includes/inc_footer.php");

/////////////////////////

function validate_request($id)
{
	global $db;

	$row = $db->GetRow('SELECT tc.*, c.id_user
		FROM type_contact tc, contact c
		WHERE tc.id = c.id_type_contact
			AND c.id = ' . $id);

	if (!$row['protect'] && $row['abbrev'] != 'mail')
	{
		return true;
	}

	$count = $db->GetOne('SELECT COUNT(*)
		FROM contact
		WHERE id_type_contact = ' . $row['id'] . '
			AND id_user = ' . $row['id_user']);

	return ($count >= 2) ? true : false;
}
