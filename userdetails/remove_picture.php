<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

if(isset($_POST["zend"]))
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));

	// First, grab the filename and delete the file after clearing the field
	$q1 = "SELECT \"PictureFile\" FROM users WHERE id=" . $s_id;
	$file = $db->GetOne($q1);

	// Clear the PictureFile field
	$query = "UPDATE users SET \"PictureFile\" = NULL WHERE id=" .$s_id;
	$db->Execute($query);

	if(!empty($file)){
		$result = $s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $file,
		));
		log_event($id, "Pict", "Removing old picture file " . $file);
	}
	$msg = "Removed picture " .$file;
	log_event($s_id, "Pict",$msg);

	readuser($s_id, true);

	$alert->success("Foto verwijderd.");
	header("Location:  mydetails.php");
	exit;
}

echo "<h1>Foto verwijderen</h1>";

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';
echo "<form method='POST'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
echo "<tr>\n";
echo "<td>Foto verwijderen? <input type='submit' value='Foto verwijderen' name='zend'></td>\n";
echo "</tr>\n\n</table>";
echo "</form>";

echo '</div>';
echo '</div>';

