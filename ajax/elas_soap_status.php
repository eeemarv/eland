<?php
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$group_id = $_GET['group_id'];

header('Content-type: text/plain');

if (!$s_schema)
{
	echo 'Onvoldoende rechten.';
	exit;
}

if (!$group_id)
{
	echo 'Het groep id ontbreekt.';
	exit;
}

$group = $db->fetchAssoc('SELECT * FROM ' . $s_schema . '.letsgroups WHERE id = ?', array($group_id));

if (!$group)
{
	echo 'Groep niet gevonden.';
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	echo 'De apimethod voor deze groep is niet elassoap.';
	exit;
}

if (!$group['remoteapikey'])
{
	echo 'De remote apikey is niet ingesteld voor deze groep.';
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
	echo $m . ' ' . $err;
	log_event('token', $m . ' ' . $err);
	exit;
}

$message = $client->call('getstatus', array('apikey' => $apikey));

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen status verkrijgen. ' . $err;
	echo $m;
	log_event('token', $m . ' ' . $err);
	exit;
}

echo $message;
exit;
