<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$msgid = $_GET["id"];
$validity = $_GET["validity"];

if(isset($msgid)){
	$posted_list["vtime"] = count_validity($validity);
	if (update_msg($msgid, $posted_list, $s_id))
	{
		$alert->success('Vraag of aanbod is verlengd.');
	}
	else
	{
		$alert->error('Vraag of aanbod is niet verlengd.');
	}
}
header("Location:  mymsg_overview.php");
exit;

//////////////////

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
	//echo "Message ID: $id";
    return $db->AutoExecute("messages", $posted_list, 'UPDATE', "id=$id");
}

