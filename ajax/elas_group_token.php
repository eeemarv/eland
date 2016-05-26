<?php
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$group_id = $_GET['group_id'];

header('Content-type: application/json');

if (!$group_id)
{
	echo json_encode(array('error' => 'Het groep id ontbreekt.'));
	exit;
}

if (!$elas_interlets_groups[$group_id])
{
	echo json_encode(array('error' => 'Er is geen interletsconnectie met deze groep.'));
	exit;
}

$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($group_id));

if (!$group)
{
	echo json_encode(array('error' => 'Groep niet gevonden.'));
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	echo json_encode(array('error' => 'De apimethod voor deze groep is niet elassoap.'));
	exit;
}

if (!$group['remoteapikey'])
{
	echo json_encode(array('error' => 'De remote apikey is niet ingesteld voor deze groep.'));
	exit;
}

if (!$group['presharedkey'])
{
	echo json_encode(array('error' => 'De preshared key is niet ingesteld voor deze groep.'));
	exit;
}

$soapurl = ($group['elassoapurl']) ? $group['elassoapurl'] : $group['url'] . '/soap';
$soapurl = $soapurl . '/wsdlelas.php?wsdl';

$apikey = $group['remoteapikey'];

$client = new nusoap_client($soapurl, true);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen verbinding maken.';
	echo json_encode(array('error' => $m));
	log_event($s_id, 'token', $m . ' ' . $err);
	exit;
}

$token = $client->call('gettoken', array('apikey' => $apikey));

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen token krijgen.';
	echo json_encode(array('error' => $m));
	log_event($s_id, 'token', $m . ' ' . $err);
	exit;
}

echo json_encode(array('token' => $token));
exit;
