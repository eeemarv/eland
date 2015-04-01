<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$letsgroup_id = $_GET['letsgroup_id'];
$location = $_GET['location'];

if ($letsgroup_id)
{
	$letsgroup = $db->GetRow('SELECT * FROM letsgroups WHERE id = ' . $letsgroup_id);

	$err_group = $letsgroup['groupname'] . ': ';

	if($letsgroup["apimethod"] == 'elassoap')
	{
		$soapurl = ($letsgroup['elassoapurl']) ? $letsgroup['elassoapurl'] : $letsgroup['url'] . '/soap';
		$soapurl = $soapurl ."/wsdlelas.php?wsdl";
		$apikey = $letsgroup["remoteapikey"];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if ($err)
		{
			$alert->error($err_group . 'Kan geen verbinding maken.');
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				$alert->error($err_group . 'Kan geen token krijgen.');
			}
			else
			{
				echo '<script>window.open("' . $letsgroup['url'] . '/login.php?token=' . $token . '&location=' . $location . '");</script>';
			}
		}
	}
	else
	{
		$alert->error($err_group . 'Deze groep draait geen eLAS-soap, kan geen connectie maken');
	}
}

$letsgroups = $db->GetArray('SELECT * FROM letsgroups WHERE apimethod <> \'internal\'');

include($rootpath."includes/inc_header.php");

echo "<h1>Andere interlets groepen raadplegen</h1>";
echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";

foreach($letsgroups as $key => $value){
	echo "<tr><td nowrap>";
	echo '<a href="?letsgroup_id=' . $value['id'] . '">' .$value["groupname"] . '</a>';
	echo "</td></tr>";
}

echo "</table>";

include($rootpath."includes/inc_footer.php");
