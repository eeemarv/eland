<?php

ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$id = $_GET["id"];
if(empty($id))
{
	header('Location: ' . $rootpath . 'messages/overview.php');
	exit;
}

$msg = $db->GetRow('SELECT m.*, u.name as username, u.letscode, ct.fullname
	FROM messages m, users u, categories ct
	WHERE m.id = ' .$id . '
		AND m.id_category = ct.id
		AND m.id_user = u.id');

if ($role == 'user' && $s_id != $msg['id_user'])
{
	$alert->warning('Je hebt onvoldoende rechten om het vraag of aanbod te verwijderen.');
	header('Location: ' . $rootpath . 'overview.php');
	exit;
}	

if(isset($_POST["zend"]))
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));

	$pictures = $db->Execute("SELECT * FROM msgpictures WHERE msgid = ".$id);
	foreach($pictures as $value)
	{
		$s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $value['PictureFile'],
		));
	}

	$db->Execute("DELETE FROM msgpictures WHERE msgid = ".$id );

	$result = $db->Execute("DELETE FROM messages WHERE id =".$id );

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

echo "<h1>Vraag & Aanbod verwijderen</h1>";
echo "<div >";
echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
echo "<tr class='header'>";
echo "<td valign='top' nowrap><strong>V/A</strong></td>";
echo "<td valign='top' nowrap><strong>Wat</strong></td>";
echo "<td valign='top' nowrap><strong>Wie</strong></td>";
echo "<td valign='top' nowrap><strong>Geldig tot</strong></td>";
echo "<td valign='top' nowrap><strong>Categorie</strong></td>";
echo "</tr>";

echo "<tr>";
echo "<td valign='top' nowrap>";
	if ($msg["msg_type"] == 0){
	echo "V ";
}elseif($msg["msg_type"] == 1){
	echo "A ";
}
echo "</td>";
echo "<td valign='top'>";
echo nl2br(htmlspecialchars($msg["content"],ENT_QUOTES));
echo "</td>";
echo "<td valign='top' nowrap>";
echo htmlspecialchars($msg["username"],ENT_QUOTES)." (".trim($msg["letscode"]).")<br>";
echo "</td>";
echo "<td valign='top' nowrap>";
echo $msg["validity"];
echo "</td>";
echo "<td valign='top'>";
echo htmlspecialchars($msg["fullname"],ENT_QUOTES);
echo "</td>";
echo "</tr>";
echo "</table></div>";

echo "<p><font color='red'><strong>Ben je zeker dat ";
if($msg["msg_type"] == 0){
	echo "deze vraag";
}elseif($msg["msg_type"] == 1){
	echo "dit aanbod";
}
echo " moet verwijderd worden?</strong></font></p>";
echo "<div class='border_b'><p><form action='delete.php?id=".$id."' method='POST'>";
echo "<input type='submit' value='Verwijderen' name='zend'>";
echo "</form></p>";
echo "</div>";

include($rootpath."includes/inc_footer.php");
