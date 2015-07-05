<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(isset($s_id)){
	$id = $_GET["id"];
	if(isset($id)){
		if(isset($_POST["zend"])){
			update_user($id,$rootpath);
			$alert->success("Foto verwijderd.");
			header("Location:  mydetails.php");
		}else{
			echo "<h1>Foto verwijderen</h1>";
			show_form($id);
		}
	}else{
		redirect_view();
	}
}else{
	redirect_login($rootpath);
}



function update_user($id, $rootpath){
	global $db;

	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));

	// First, grab the filename and delete the file after clearing the field
	$q1 = "SELECT \"PictureFile\" FROM users WHERE id=" .$id;
	$file = $db->GetOne($q1);

	// Clear the PictureFile field
	$query = "UPDATE users SET \"PictureFile\" = NULL WHERE id=" .$id;
	$db->Execute($query);

	if(!empty($file)){
		$result = $s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $file,
		));
		log_event($id, "Pict", "Removing old picture file " . $file);
	}
	$msg = "Removed picture " .$file;
	log_event($id, "Pict",$msg);

	readuser($id, true);
}

function show_form($user){
	echo "<form action='remove_picture.php?id=".$user ."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr>\n";
	echo "<td>Foto verwijderen? <input type='submit' value='Foto verwijderen' name='zend'></td>\n";
	echo "</tr>\n\n</table>";
	echo "</form>";
	echo '<p>&nbsp;</p>';
}

function get_user($id){
   return readuser($id);
}

function redirect_view(){
	header("Location: mydetails.php");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
