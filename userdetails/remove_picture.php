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

if(isset($s_id)){
	show_ptitle();
	$id = $_GET["id"];
	if(isset($id)){
		if(isset($_POST["zend"])){
			update_user($id,$rootpath);
			//echo "<script type=\"text/javascript\">self.close(); window.opener.location.reload()</script>";
			setstatus("Foto verwijderd",0);
			header("Location:  mydetails.php");
		}else{
			show_form($id);
		}
	}else{ 
		redirect_view();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Foto verwijderen</h1>";
}

function update_user($id, $rootpath){
	global $db;
	global $baseurl;
	// First, grab the filename and delete the file after clearing the field
	$q1 = "SELECT \"PictureFile\" FROM users WHERE id=" .$id;
	$myuser = $db->GetRow($q1);
	
	// Clear the PictureFile field
	$query = "UPDATE users SET \"PictureFile\" = NULL WHERE id=" .$id;
	$db->Execute($query);

	// unlink the file that was in the field.
	if(!empty($myuser['PictureFile'])){
		delete_file($rootpath, $myuser['PictureFile']);
	}
	$msg = "Removed picture " .$myuser['PictureFile'];
	log_event($id,"Pict",$msg);
	echo "Foto verwijderd.";

	readuser($id, true);	
}

function delete_file($rootpath, $file){
	global $baseurl;
	global $dirbase;
	$target =  $rootpath ."sites/$dirbase/userpictures/".$file;
	echo "Foto file $target wordt verwijderd...<br>";
	unlink($target);
}

function show_form($user){
	echo "<form action='remove_picture.php?id=".$user ."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr>\n";
	echo "<td>Foto verwijderen? <input type='submit' value='Foto verwijderen' name='zend'></td>\n";
	echo "</tr>\n\n</table>";
	echo "</form>";
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

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>


