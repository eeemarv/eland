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

//include($rootpath."includes/inc_smallheader.php");
//include($rootpath."includes/inc_content.php");

$id = $_POST["id"];

if(isset($s_id)) {
	//echo "<font color='green'><strong>OK</font> - Transactie opgeslagen</strong></font>";
	$picture = get_picture($id);
	$msg = get_msg($picture["msgid"]);
	if($msg["id_user"] == $s_id || $s_accountrole == "admin"){
		if(delete_record($id) == TRUE){
			delete_file($picture["PictureFile"]);
			echo "<font color='green'><strong>OK</font> - Foto $id verwijderd</strong></font>";
		} else {
			echo "<font color='red'><strong>Fout bij het verwijderen van foto $id</strong></font>";
		}
	} else {
		echo "<font color='red'><strong>Fout: Geen rechten op deze foto</strong></font>";
	}
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function delete_file($file){
	global $rootpath;
	global $baseurl;
	global $dirbase;
	$target =  $rootpath ."sites/$dirbase/msgpictures/".$file;
	unlink($target);
}

function delete_record($id){
	global $db;
        $query = "DELETE FROM msgpictures WHERE id=" .$id;
	$result = $db->Execute($query);
	return $result;
}

function get_picture($id){
        global $db;
        $query = "SELECT * FROM msgpictures WHERE id = " .$id;
        $picture = $db->GetRow($query);
        return $picture;
}

function get_msg($msgid){
        global $db;
        $query = "SELECT * , ";
        $query .= " messages.cdate AS date, ";
        $query .= " messages.validity AS valdate";
        $query .= " FROM messages, users ";
        $query .= " WHERE messages.id = ". $msgid;
        $query .= " AND messages.id_user = users.id ";
        $message = $db->GetRow($query);
        return $message;
}

?>
