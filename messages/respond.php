<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_mailfunctions.php");

if(isset($s_id)){
	$msgid = $_POST["msgid"];
	$message = get_msg($msgid);
	$reactie = $_POST["reactie"];
	$cc = $_POST["cc"];

	composemail($s_id, $message, $reactie, $cc);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function composemail($s_id, $message, $reactie, $cc){
	global $_POST;
	$systemtag = readconfigfromdb("systemtag");
	$user = get_user($message["id_user"]);

	$me = get_user($s_id);

	$contact = get_contact($s_id);
	$usermail = get_user_maildetails($message["id_user"]);
	$my_mail = get_user_maildetails($s_id);

    $mailsubject = "[eLAS-".$systemtag ."] - Reactie op je V/A " .$message["content"];
	$mailfrom = $my_mail["emailaddress"];

	if($cc == "true"){
		$mailto =  $usermail["emailaddress"] ."," .$my_mail["emailaddress"];
	} else {
		$mailto =  $usermail["emailaddress"];
	}

	$mailcontent = "Beste " .$user["fullname"] ."\r\n\n";
	$mailcontent .= "-- " .$me["fullname"] ." heeft een reactie op je vraag/aanbod verstuurd via eLAS --\r\n\n";
	$mailcontent .= "$reactie\n\n";

	$mailcontent .= "* Om te antwoorden kan je gewoon reply kiezen of de contactgegevens hieronder gebruiken\n";
	$mailcontent .= "* Contactgegevens van ".$me["fullname"] .":\n";

        foreach($contact as $key => $value){
		$mailcontent .= "* " .$value["abbrev"] ."\t" .$value["value"] ."\n";
        }

	$mailstatus = sendemail($mailfrom, $mailto, $mailsubject, $mailcontent, 1);

	setstatus("$mailstatus",0);
}

function get_msg($msgid){
	global $db;
	$query = "SELECT * , ";
	$query .= " messages.cdate AS date ";
	$query .= " FROM messages, users ";
	$query .= " WHERE messages.id = ". $msgid;
	$query .= " AND messages.id_user = users.id ";
	$message = $db->GetRow($query);
	return $message;
}

?>
