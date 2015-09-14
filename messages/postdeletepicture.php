<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");

$id = $_POST['id'];

$picture = $db->fetchAssoc('select * from msgpictures where id = ?', array($id));

$user_id = $db->fetchColumn('select id_user from messages where id = ?', array($picture['msgid']));

if($user_id == $s_id || $s_accountrole == "admin")
{
	if($db->delete('msgpictures', array('id' => $id))
	{
		$s3 = Aws\S3\S3Client::factory(array(
			'signature'	=> 'v4',
			'region'	=> 'eu-central-1',
			'version'	=> '2006-03-01',
		));

		$result = $s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $schema . '_m_' . $picture["PictureFile"],
		));
		
		echo "<font color='green'><strong>OK</font> - Foto $id verwijderd</strong></font>";
	}
	else
	{
		echo "<font color='red'><strong>Fout bij het verwijderen van foto $id</strong></font>";
	}
}
else
{
	echo "<font color='red'><strong>Fout: Geen rechten op deze foto</strong></font>";
}
