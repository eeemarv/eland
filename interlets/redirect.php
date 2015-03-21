<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

$groupid = $_GET["letsgroup"];

include($rootpath."includes/inc_smallheader.php");

$letsgroup = get_letsgroup($groupid);


echo "<h1>Interlets login naar " . $letsgroup['groupname'] . "</h1>";

if($letsgroup["apimethod"] != 'elassoap')
{	
	$alert->error('Deze groep draait geen eLAS, kan geen connectie maken');
	echo '<script>self.close(); window.opener.location.reload();</script>';
}

$soapurl = ($letsgroup['elassoapurl']) ?: $letsgroup['url'] . '/soap';
$soapurl = $soapurl ."/wsdlelas.php?wsdl";
$apikey = $letsgroup["remoteapikey"];
$client = new nusoap_client($soapurl, true);
$err = $client->getError();
if ($err)
{
	$alert->error('Kan geen verbinding maken.');
	echo '<script>self.close(); window.opener.location.reload();</script>';
}
	
$token = $client->call('gettoken', array('apikey' => $apikey));
$err = $client->getError();
if ($err)
{
	$alert->error('Kan geen token krijgen.');
	echo '<script>self.close(); window.opener.location.reload();</script>';
}

if (!$token)
{
	$alert->error('Geen token verkregen.');
	echo '<script type="text/javascript">self.close(); window.opener.location.reload();</script>';
}	


header('Location: ' . $letsgroup["url"] ."/login.php?token=" . $token);
exit;

/*	
echo "<script type='text/javascript' src='/js/redirect.js'></script>";
$url = "/interlets/doredirect.php?letsgroup=" .$groupid;
echo "<div id='output'>Connectie met eLAS wordt gemaakt... <img src='/gfx/ajax-loader.gif' ALT='loading'>";
echo "<script type='text/javascript'>doredirect('$url');</script>";
echo "</div>";*/






