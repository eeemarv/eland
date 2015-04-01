<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$id = $_GET["id"];

if(isset($s_id)) {
	show_header();
	$picture = get_picture($id);
	$msg = get_msg($picture["msgid"]);
	if($msg["id_user"] == $s_id || $s_accountrole == "admin"){
		show_button($id);
	}
	show_picture($picture["PictureFile"]);
	show_closebutton();
	show_footer();
}

////////////////////////////////////////////////////////////////////////////

function show_header(){
	global $rootpath;
	echo "<html><head></head><body>";
	echo "<link rel='stylesheet' href='".$rootpath."gfx/main.css'>";
	echo "<link rel='stylesheet' href='".$rootpath."gfx/menu.css'>";
}

function show_footer(){
	echo "</body></html>";
}

function show_button($id){
	echo "<script type='text/javascript' src='/js/deletemsgpicture.js'></script>";
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="edit.php?mode=edit&id=$msgid";
        echo "<li><a href='#' onclick=\"deletepicture($id);\">Verwijderen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_closebutton(){
        echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
        echo "<input type='button' id='close' value='Sluiten' onclick='self.close(); window.opener.location.reload();'>";
        echo "<form></td></tr></table>";
}

function show_picture($file)
{
	echo "<div id='picdiv'>";
	$url = 'https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $file;
	echo "<img src='" .$url ."' width='640'>";
	echo "</div>";
}

function get_picture($id)
{
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
