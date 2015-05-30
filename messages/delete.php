<?php

ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET["id"];

if(empty($id))
{
	header('Location: ' . $rootpath . 'messages/overview.php');
	exit;
}

$msg = $db->GetRow('SELECT m.*, u.name as username, u.letscode, ct.fullname as catname
	FROM messages m, users u, categories ct
	WHERE m.id = ' .$id . '
		AND m.id_category = ct.id
		AND m.id_user = u.id');

if ($s_accountrole == 'user' && $s_id != $msg['id_user'])
{
	$alert->warning('Je hebt onvoldoende rechten om het vraag of aanbod te verwijderen.');
	header('Location: ' . $rootpath . 'messages/view.php?id=' . $id);
	exit;
}	

if(isset($_POST["zend"]))
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
	));

	$pictures = $db->Execute("SELECT * FROM msgpictures WHERE msgid = ".$id);
	foreach($pictures as $value)
	{
		$s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $value['PictureFile'],
		));
	}

	$db->Execute('DELETE FROM msgpictures WHERE msgid = '.$id );

	$result = $db->Execute('DELETE FROM messages WHERE id = '.$id );

	if ($result)
	{
		$column = 'stat_msgs_';
		$column .= ($msg['msg_type']) ? 'offers' : 'wanted';

		$db->Execute('update categories
			set ' . $column . ' = ' . $column . ' - 1
			where id = ' . $msg['id_category']);
	}

	if ($result)
	{
		$alert->success('Vraag / aanbod verwijderd.');
		header('Location: ' . $rootpath . 'messages/overview.php');
		exit;
	}

	$alert->error('Vraag / aanbod niet verwijderd.');
}

$h1 = ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
$h1 .= ': ' . htmlspecialchars($msg['content'], ENT_QUOTES);
$h1 .= ' verwijderen?';
$fa = 'leanpub';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-defaut"><div class="panel-body">';
echo htmlspecialchars($msg['Description'], ENT_QUOTES);
echo '</div></div>';

echo '<dl>';

echo '<dt>Wie</dt>';
echo '<dd>';
echo htmlspecialchars($msg['letscode'] . ' ' . $msg['username'], ENT_QUOTES);
echo '</dd>';

echo '<dt>Categorie</dt>';
echo '<dd>';
echo htmlspecialchars($msg['catname'], ENT_QUOTES);
echo '</dd>';

echo '<dt>Geldig tot</dt>';
echo '<dd>';
echo $msg['validity'];
echo '</dd>';
echo '</dl>';

echo '<div class="label label-warning">';
echo 'Ben je zeker dat ';
echo ($msg['msg_type']) ? 'dit aanbod' : 'deze vraag';
echo ' moet verwijderd worden?</div><br><br>';

echo '<form method="post">';
echo '<a href="' . $rootpath . 'messages/view.php?id=' . $id . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo "</form></p>";

include $rootpath . 'includes/inc_footer.php';
