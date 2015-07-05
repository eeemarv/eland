<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$msgid = $_GET["msgid"];

$s3 = Aws\S3\S3Client::factory(array(
	'signature'	=> 'v4',
	'region'	=>'eu-central-1',
	'version'	=> '2006-03-01',
));
$bucket = getenv('S3_BUCKET')?: die('No "S3_BUCKET" config var in found in env!');

$sizelimit = 200;

if (isset($_POST["zend"])){
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
		
        $upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read');
        
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
		/*
		if($file_size > ($sizelimit * 1024)) {
			//Resize the image first
						echo "Je foto is te groot, bezig met verkleinen...<br>";
			resizepic($file, $tmpfile, $rootpath, $msgid);
		} else {
			//echo "Foto voor Message " .$msgid;
			place_picture($file, $tmpfile, $rootpath, $msgid);
		} */
	} else {
		$alert->error("Het bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd.");
	}

	header('Location: ' . $rootpath . 'messages/view.php?id=' . $msgid);
	exit;
}

echo "<h1>Foto aan V/A toevoegen</h1>";
show_form($msgid);


////////////////////////////////////////////////////////////////////////////

function show_form($msgid)
{
	echo '<form action="upload_picture.php?msgid=' . $msgid . '" enctype="multipart/form-data" method="POST">' . "\n";
	echo "<input name='picturefile' type='file' required>\n";
	echo "<input type='submit' name='zend' value='Versturen' />\n";
	echo "</form>\n";
	echo "LET OP: Je foto moet in het jpeg (jpg) formaat zijn en mag maximaal 200kB groot zijn.";
	echo '<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>';
}

function place_picture($file, $tmpfile, $rootpath, $msgid){
	global $baseurl;
	global $dirbase;
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	// Limit file size
	// Check if the file is already there.
	$ts = time();
	$uploadfile =  $rootpath ."sites/$dirbase/msgpictures/" .$msgid ."_" .$ts ."." .$ext;
	if(file_exists($uploadfile)){
		echo "<font color='red'>Het bestand bestaat al, hernoem je bestand en probeer opnieuw.</font>";
	} else {
		if (!move_uploaded_file($tmpfile  , $uploadfile) ){
    			echo "Foto uploaden is niet gelukt...\n";
		} else {
			echo "Foto opgeladen, wordt toegevoegd aan je profiel...<br>";
			$target = $msgid ."_" .$ts ."." .$ext;
			dbinsert($msgid, $target, $rootpath);
		}
	}
}

function resizepic($file, $tmpfile, $rootpath, $msgid){
	global $baseurl;
	global $dirbase;
        $ext = pathinfo($file, PATHINFO_EXTENSION);

	$src = imagecreatefromjpeg($tmpfile);
	list($width,$height)=getimagesize($tmpfile);
	$newwidth=800;
	$newheight=($height/$width)*$newwidth;
	$tmp=imagecreatetruecolor($newwidth,$newheight);
	imagecopyresampled($tmp,$src,0,0,0,0,$newwidth,$newheight,$width,$height);
        $ts = time();
        $uploadfile =  $rootpath ."sites/$dirbase/msgpictures/" .$msgid ."_" .$ts ."." .$ext;
	//$uploadfile =  $rootpath ."userpictures/" .$msgid ."_" .$file;
        if(file_exists($uploadfile)){
                echo "<font color='red'>Het bestand bestaat al, hernoem je bestand en probeer opnieuw.</font>";
        } else {
		imagejpeg($tmp,$uploadfile,100);
		echo "Foto opgeladen, wordt toegevoegd aan je profiel...<br>";
		$target = $msgid ."_" .$ts ."." .$ext;
		//$target = $msgid ."_" .$file;
		dbinsert($msgid, $target,$rootpath);
		imagedestroy($src);
		imagedestroy($tmp);
	}
}

