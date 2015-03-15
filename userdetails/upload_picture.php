<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!isset($s_id))
{
	exit;
}

if (!($s_accountrole == 'user' || $s_accountrole == 'admin'))
{
	exit;
}

$sizelimit = 200;

if (!isset($_POST["zend"])){
	echo "<h1>Foto aan profiel toevoegen</h1>";
	show_form($sizelimit);	
}
else
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=>'eu-central-1',
	));
	$bucket = getenv('S3_BUCKET')?: die('No "S3_BUCKET" config var in found in env!');
		
	$tmpfile = $_FILES['picturefile']['tmp_name'];
	$file = $_FILES['picturefile']['name'];
	$file_size=$_FILES['picturefile']['size'];

	$ext = pathinfo($file, PATHINFO_EXTENSION);

	if(!($ext == "jpeg" || $ext == "JPEG" || $ext == "jpg" || $ext == "JPG"))
	{
		$alert->error('Bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd.');
		header("Location:  mydetails.php");
		exit;
	}
	else
	{	// FIX ME (move to client side)
		if($file_size > ($sizelimit * 1024))
		{
			
			$src = imagecreatefromjpeg($tmpfile);
			list($width,$height)=getimagesize($tmpfile);
			$newwidth=300;
			$newheight=($height/$width)*$newwidth;
			$tmp=imagecreatetruecolor($newwidth,$newheight);
			imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);
			imagejpeg($tmp,$tmpfile,100);
			imagedestroy($src);
			imagedestroy($tmp);
		}

		try {
			$filename = $session_name . '_u_' . $s_id . '_' . sha1(time()) . '.' . $ext;

			$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read');

			$old = $db->GetOne('SELECT "PictureFile" FROM users WHERE id=' . $s_id);

			$query = 'UPDATE users SET "PictureFile" =  \'' . $filename . '\' WHERE id = ' . $s_id;
			$db->Execute($query);

			readuser($s_id, true);

			log_event($s_id, "Pict", "Picture $filename uploaded");

			if(!empty($old)){
				$result = $s3->deleteObject(array(
					'Bucket' => getenv('S3_BUCKET'),
					'Key'    => $old,
				));
				log_event($s_id, "Pict", "Removing old picture file " . $old);
			}

			$alert->success('Foto toegevoegd.');

			//header("Location: ".$rootpath ."userdetails/mydetails_view.php");
			header("Location:  mydetails.php");
			exit;
		}
		catch(Exception $e)
		{ 
			echo '<p>Upload error :(</p>';
			log_event($s_id, 'Pict', 'Upload fail : ' . $e->getMessage());
		}
	}
}


////////////////////////////////////////////////////////////////////////////


function show_form($sizelimit){
	echo "<form action='upload_picture.php' enctype='multipart/form-data' method='POST'>\n";
    echo "<input name='picturefile' type='file'/>\n";
	echo "<input type='submit' name='zend' value='Versturen'/>\n";
	echo "</form>\n";
	echo '<p>LET OP: Je foto moet in het jpeg (jpg) formaat en mag maximaal ' . $sizelimit . 'kB groot zijn </p>';
	echo '<p>&nbsp;</p>';
}

function place_picture($file,$tmpfile,$rootpath,$id){
	global $baseurl;
	global $dirbase;
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	$ts = time();
	// Limit file size
	// Check if the file is already there.
	$uploadfile =  $rootpath ."sites/$dirbase/userpictures/" .$id ."_" .$ts ."." .$ext;
	if(file_exists($uploadfile)){
		echo "<font color='red'>Het bestand bestaat al, hernoem je bestand en probeer opnieuw.</font>";
	} else {
		if (!move_uploaded_file($tmpfile  , $uploadfile) ){
    			echo "Foto uploaden is niet gelukt...\n";
		} else {
			echo "Foto opgeladen, wordt toegevoegd aan je profiel...<br>";
			$target = $id ."_" .$ts ."." .$ext;
			dbinsert($id, $target,$rootpath);
		}
	}
}

function resizepic($file,$tmpfile,$rootpath, $id){
	global $baseurl;
	global $dirbase;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $ts = time();
	$src = imagecreatefromjpeg($tmpfile);
	list($width,$height)=getimagesize($tmpfile);
	$newwidth=300;
	$newheight=($height/$width)*$newwidth;
	$tmp=imagecreatetruecolor($newwidth,$newheight);
	imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);
	//$uploadfile =  $rootpath ."userpictures/" .$id ."_" .$file;
	$uploadfile =  $rootpath ."sites/$dirbase/userpictures/" .$id ."_" .$ts ."." .$ext;
        if(file_exists($uploadfile)){
                echo "<font color='red'>Het bestand bestaat al, hernoem je bestand en probeer opnieuw.</font>";
        } else {
		imagejpeg($tmp,$uploadfile,100);
		echo "Foto opgeladen, wordt toegevoegd aan je profiel...<br>";
		//$target = $id ."_" .$file;
		$target = $id ."_" .$ts ."." .$ext;
		dbinsert($id, $target,$rootpath);
		imagedestroy($src);
		imagedestroy($tmp);
	}
}

function dbinsert($userid, $file, $rootpath) {
	global $db;
	global $_SESSION;
	// Save the old filename for cleanup
        $q1 = 'SELECT \'PictureFile\' FROM users WHERE id=' .$userid;
        $myuser = $db->GetRow($q1);

        $query = 'UPDATE users SET \'PictureFile\' = ' .$file . ' WHERE id=' .$userid;
	$db->Execute($query);
	log_event($userid,"Pict","Picture $file uploaded");

	// Delete the old file
	if(!empty($myuser['PictureFile'])){
                delete_file($rootpath, $myuser['PictureFile']);
		$msg = "Removing old picture file " .$myuser['PictureFile'];
		log_event($userid,"Pict",$msg);
	}

	// Redirect
	setstatus("Foto toegevoegd", 0);

	readuser($userid, true);

	//header("Location: ".$rootpath ."userdetails/mydetails_view.php");
	header("Location:  mydetails.php");
        //echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
}

function delete_file($rootpath, $file){
        $target =  $rootpath ."userpictures/".$file;
        echo "Foto file $target wordt verwijderd...<br>";
        unlink($target);
}

function getuserid($letscode){
        global $db;
	$query = "SELECT id FROM users WHERE letscode = '" .$letscode."'";
	$user = $db->GetRow($query);
	return $user["id"];
}

//include($rootpath."includes/inc_smallfooter.php");
