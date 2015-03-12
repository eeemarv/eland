<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

if(!isset($s_id)){
	exit;
}

$letscode= $_GET["letscode"];
if(empty($_GET["letsgroup"])){
//	$letsgroup = readconfigfromdb("myinterletsid"); doesn't exist necessarily
	$letsgroup = $db->GetOne('SELECT id FROM letsgroups WHERE apimethod = \'internal\'');
} else {
	$letsgroup= $_GET["letsgroup"];
}

$myletsgroup = get_letsgroup($letsgroup);

$mysoapurl = $myletsgroup["elassoapurl"] ."/wsdlelas.php?wsdl";
$myapikey = $myletsgroup["remoteapikey"];
$client = new nusoap_client($mysoapurl, true);
$err = $client->getError();
if (!$err) {
	$result = $client->call('userbyletscode', array('apikey' => $myapikey, 'letscode' => $letscode));
	$err = $client->getError();
    	if (!$err) {
		echo $result;
	}
}
