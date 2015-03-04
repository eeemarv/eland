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

$groupid = $_GET["letsgroup"];

if(isset($s_id)){
	$letsgroup = get_letsgroup($groupid);
	$mytoken = gettoken($letsgroup);
	$myurl = $letsgroup["url"] ."/login.php?token=" . $mytoken;
	//header("Location: $myurl");
	echo $myurl;
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

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
