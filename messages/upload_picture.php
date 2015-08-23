<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$msgid = $_GET['msgid'];

if (!$msgid)
{
	exit;
}

$s3 = Aws\S3\S3Client::factory(array(
	'signature'	=> 'v4',
	'region'	=> 'eu-central-1',
	'version'	=> '2006-03-01',
));
$bucket = getenv('S3_BUCKET') ?: die('No "S3_BUCKET" env config var in found!');

$sizelimit = 200;

$msg = $db->GetArray('select id_user, msg_type from messages where id = ' . $msgid);

if (!($s_accountrole == 'admin' || $msg['id_user'] == $s_id))
{
	exit;
}

if (isset($_POST['zend']))
{
	$tmpfile = $_FILES['picturefile']['tmp_name'];
	$file = $_FILES['picturefile']['name'];

	$file_size=$_FILES['picturefile']['size'];
	
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	if ($file_size > $sizelimit * 1024)
	{
		$alert->error('Het bestand is te groot. De maximum grootte is 200kB.');
		header('Location:  ' . $rootpath . 'messages/view.php?id=' . $msgid);
		exit;
	}

	if($ext == "jpeg" || $ext == "jpg"){

		try {
			$filename = $schema . '_m_' . $msgid . '_' . sha1(time()) . '.jpg';
			
			$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
				'params'	=> array(
					'CacheControl'	=> 'public, max-age=31536000',
				),
			));
			
			$query = 'INSERT INTO msgpictures (msgid, "PictureFile") VALUES (' . $msgid . ', \'' . $filename . '\')';
			$db->Execute($query);
			log_event($s_id, "Pict", "Message-Picture $file uploaded");

			setstatus("Foto toegevoegd", 0);

			echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
			echo '<p>Upload <a href="' . htmlspecialchars($upload->get('ObjectURL')) . '">succes</a> :)</p>';
		}
		catch(Exception $e)
		{ 
			echo '<p>Upload error :(</p>';
			log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
		} 
		// resizing removed
	}
	else
	{
		$alert->error("Het bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd.");
	}

	header('Location: ' . $rootpath . 'messages/view.php?id=' . $msgid);
	exit;
}

$va = ($msg['msg_type']) ? 'aanbod' : 'vraag';

echo '<h1>Foto aan ' . $va . ' toevoegen</h1>';
echo '<form enctype="multipart/form-data" method="post" action="' . $rootpath . 'messages/upload_picture.php?msgid=' . $msgid . '">';
echo '<input name="picturefile" type="file" required accept="image/jpeg"><br><br>';
echo '<input type="submit" name="zend" value="Versturen" class="btn btn-default">';
echo '</form>';
echo '<p>LET OP: Je foto moet in het jpeg (jpg) formaat zijn en mag maximaal 200kB groot zijn.</p>';
echo '<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>';
