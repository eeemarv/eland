<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
// Pull in the NuSOAP code
require_once($rootpath."soap/lib/nusoap.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

if(!isset($s_id)){
        exit;
}

$name = $_GET["name"];

if(empty($_GET["letsgroup"])){
	$letsgroup = readconfigfromdb("myinterletsid");
} else {
	$letsgroup= $_GET["letsgroup"];
}

$myletsgroup=get_letsgroup($letsgroup);

$mysoapurl = $myletsgroup["elassoapurl"] ."/wsdlelas.php?wsdl";
$myapikey = $myletsgroup["remoteapikey"];
$client = new nusoap_client($mysoapurl, true);
$err = $client->getError();
if (!$err) {
	$result = $client->call('userbyname', array('apikey' => $myapikey, 'name' => $name));
	$err = $client->getError();
    	if (!$err) {
		echo $result;
	} else {
		echo "Mislukt";
	}
}



//debug
//print_r($_GET);
//$user = get_user_by_letscode($letscode);
//echo $user["letscode"];
?>
