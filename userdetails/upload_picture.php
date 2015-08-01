<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$sizelimit = 200;

if (isset($_POST['zend']))
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
	$bucket = getenv('S3_BUCKET') ?: die('No "S3_BUCKET" env config var in found!');
		
	$tmpfile = $_FILES['picturefile']['tmp_name'];
	$file = $_FILES['picturefile']['name'];
	$file_size=$_FILES['picturefile']['size'];

	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	if(!($ext == 'jpeg' || $ext == 'jpg'))
	{
		$alert->error('Het bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd.');
		header("Location:  mydetails.php");
		exit;
	}
	else
	{
		if ($file_size > $sizelimit * 1024)
		{
			$alert->error('Het bestand is te groot. De maximum grootte is 200kB.');
			header("Location:  mydetails.php");
			exit;
		}
		// rm resize

		try {
			$filename = $schema . '_u_' . $s_id . '_' . sha1(time()) . '.jpg';

			$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read');

			$old = $db->GetOne('SELECT "PictureFile" FROM users WHERE id=' . $s_id);

			$query = 'UPDATE users SET "PictureFile" =  \'' . $filename . '\' WHERE id = ' . $s_id;
			$db->Execute($query);

			readuser($s_id, true);

			log_event($s_id, 'Pict', 'Picture ' . $filename . 'uploaded');

			if(!empty($old)){
				$result = $s3->deleteObject(array(
					'Bucket' => getenv('S3_BUCKET'),
					'Key'    => $old,
				));
				log_event($s_id, 'Pict', 'Removing old picture file ' . $old);
			}

			$alert->success('Foto toegevoegd.');

			header('Location: ' . $rootpath .  'userdetails/mydetails.php');
			exit;
		}
		catch(Exception $e)
		{ 
			echo '<p>Upload error :(</p>';
			log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
		}
	}
}

echo '<h1>Foto aan profiel toevoegen</h1>';
echo '<form enctype="multipart/form-data" method="post" action="' . $rootpath . 'userdetails/upload_picture.php">';
echo '<input name="picturefile" type="file" required accept="image/jpeg"><br><br>';
echo '<input type="submit" name="zend" value="Versturen" class="btn btn-default">';
echo '</form>';
echo '<p>LET OP: Je foto moet in het jpeg (jpg) formaat en mag maximaal ' . $sizelimit . 'kB groot zijn </p>';
echo '<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>';
