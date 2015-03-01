<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

if(isset($s_id)) {
	show_ptitle();
	$sizelimit = 200;
        if (isset($_POST["zend"])){
		//echo $_FILES['csvfile']['name'];
		//echo $_FILES['csvfile']['tmp_name'];  
		//print_r($_FILES);
		$tmpfile = $_FILES['picturefile']['tmp_name'];
		$file = $_FILES['picturefile']['name'];
		#echo "Bestand doorgestuurd als $file<br>";
		$table = $_POST["table"];
		$file_size=$_FILES['picturefile']['size'];
		// Check the file type first
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		//echo "Extension is $ext";
		if($ext == "jpeg" || $ext == "JPEG" || $ext == "jpg" || $ext == "JPG"){
			if($file_size > ($sizelimit * 1024)) {
				//Resize the image first
	                        echo "Je foto is te groot, bezig met verkleinen...<br>";
				resizepic($file,$tmpfile,$rootpath, $s_id);
			} else {
				place_picture($file,$tmpfile,$rootpath, $s_id);
			}
		} else {
			echo "<font color='red'>Bestand is niet in jpeg (jpg) formaat, je foto werd niet toegevoegd</font>";
			setstatus("Fout: foto niet toegevoegd",1);
		}
	
	} else {
		show_form($sizelimit);
	}
	//$posted_list = array();
}else{
	echo "<script type=\"text/javascript\">self.close();</script>";
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Foto aan profiel toevoegen</h1>";
}

function show_form($sizelimit){
	echo "<form action='upload_picture.php' enctype='multipart/form-data' method='POST'>\n";
        echo "<input name='picturefile' type='file' />\n";
	echo "<input type='submit' name='zend' value='Versturen' />\n";

	echo "</form>\n";

	echo "LET OP: Je foto moet in het jpeg (jpg) formaat zijn";
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
        $q1 = "SELECT PictureFile FROM users WHERE id=" .$userid;
        $myuser = $db->GetRow($q1);

        $query = "UPDATE users SET \"PictureFile\" = '" .$file ."' WHERE id=" .$userid;
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

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
