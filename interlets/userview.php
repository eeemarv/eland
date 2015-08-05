<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

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

$top_buttons = '';

if ($s_accountrole == 'admin')
{
	$top_buttons .= '<a href="' . $rootpath . 'interlets/overview.php" class="btn btn-default"';
	$top_buttons .= ' title="Beheer letsgroepen"><i class="fa fa-cog"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Admin</span></a>';
}

$h1 = 'Interlets groepen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

if (count($letsgroups))
{
	echo '<div class="table-responsive">';
	echo '<table class="table table-striped table-bordered table-hover">';
	foreach ($letsgroups as $key => $value)
	{
		echo '<tr><td>';
		echo '<a href="?letsgroup_id=' . $value['id'] . '">' .$value["groupname"] . '</a>';
		echo "</td></tr>";
	}
	echo '</table></div>';
}
else
{
	echo '<p>Er zijn geen verbindingen met andere letsgroepen.</p>';
}

include($rootpath."includes/inc_footer.php");
