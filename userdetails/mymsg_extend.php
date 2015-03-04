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

$msgid = $_GET["id"];
$validity = $_GET["validity"];

if(isset($msgid)){
	$posted_list["vtime"] = count_validity($validity);
	update_msg($msgid, $posted_list, $s_id);
	redirect_overview();
}else{
	redirect_overview();
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function count_validity($validity){
	$valtime = time() + ($validity*30*24*60*60);
        $vtime =  date("Y-m-d H:i:s",$valtime);
        return $vtime;
}

function update_msg($id, $posted_list, $s_id){
	global $db;
	$posted_list["validity"] = $posted_list["vtime"];
	$posted_list["mdate"] = date("Y-m-d H:i:s");
	$posted_list["id_user"] = $s_id;
	echo "Message ID: $id";
    	$result = $db->AutoExecute("messages", $posted_list, 'UPDATE', "id=$id");
}

function redirect_overview(){
	header("Location:  mymsg_overview.php");
}
?>
