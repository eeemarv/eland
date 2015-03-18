<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

$groupid = $_GET["letsgroup"];

$letsgroup = get_letsgroup($groupid);
$mytoken = gettoken($letsgroup);
$myurl = $letsgroup["url"] ."/login.php?token=" . $mytoken;
//header("Location: $myurl");
echo $myurl;


//////////////////////////

function gettoken($myletsgroup){
	$mysoapurl = $myletsgroup["elassoapurl"] ."/wsdlelas.php?wsdl";
	$myapikey = $myletsgroup["remoteapikey"];
	$client = new nusoap_client($mysoapurl, true);
	$err = $client->getError();
	if (!$err) {
		$result = $client->call('gettoken', array('apikey' => $myapikey));
		$err = $client->getError();
    		if (!$err) {
			return $result;
		}
	}
}

?>
