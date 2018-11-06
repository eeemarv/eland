<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$group_id = $_GET['group_id'] ?? false;

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

$group = $app['db']->fetchAssoc('SELECT * FROM ' . $s_schema . '.letsgroups WHERE id = ?', [$group_id]);

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
	$app['monolog']->error('elas-token: ', $m . ' ' . $err);
	exit;
}

$message = $client->call('getstatus', ['apikey' => $apikey]);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen status verkrijgen. ' . $err;
	echo $m;
	$app['monolog']->error('elas-token: ', $m . ' ' . $err);
	exit;
}

echo $message;
exit;
