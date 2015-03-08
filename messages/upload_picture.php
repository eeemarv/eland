<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_smallheader.php");

$msgid = $_GET["msgid"];

if(!isset($s_id)) {
	header("Location: ".$rootpath."login.php");
}

$s3 = Aws\S3\S3Client::factory(array(
	'signature' => 'v4',
	'region'=>'eu-central-1',
));
$bucket = getenv('S3_BUCKET')?: die('No "S3_BUCKET" config var in found in env!');

show_ptitle();
$sizelimit = 3000;
if (isset($_POST["zend"])){
	$tmpfile = $_FILES['picturefile']['tmp_name'];
	$file = $_FILES['picturefile']['name'];

	$file_size=$_FILES['picturefile']['size'];
	
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	if($ext == "jpeg" || $ext == "JPEG" || $ext == "jpg" || $ext == "JPG"){

    try {
		$filename = $session_name . '_m_' . $msgid . '_' . sha1(time()) . '.' . $ext;
        $upload = $s3->upload($bucket, $filename,
			fopen($_FILES['picturefile']['tmp_name'], 'rb'), 'public-read');
		$query = 'INSERT INTO msgpictures (msgid, "PictureFile") VALUES (' . $msgid . ', \'' . $filename . '\')';
		$db->Execute($query);
		log_event($userid,"Pict","Message-Picture $file uploaded");

		setstatus("Foto toegevoegd", 0);

      //  echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
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
		echo "<font color='red'>Bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd</font>";
		setstatus("Fout: foto niet toegevoegd",1);
	}
		
} else {
	show_form($msgid);
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Foto aan V/A toevoegen</h1>";
}

function show_form($msgid){
	echo "<form action='upload_picture.php?msgid=$msgid' enctype='multipart/form-data' method='POST'>\n";
        echo "<input name='picturefile' type='file' />\n";
	echo "<input type='submit' name='zend' value='Versturen' />\n";

	echo "</form>\n";

	echo "LET OP: Je foto moet in het jpeg (jpg) formaat zijn";
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



include($rootpath."includes/inc_smallfooter.php");
