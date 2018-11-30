<?php

$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$group_id = $_GET['group_id'] ?? false;

header('Content-type: text/plain');

if (!$s_schema)
{
	echo 'Onvoldoende rechten.';
	exit;
}

if (!$group_id)
{
	echo 'Het interSysteem id ontbreekt.';
	exit;
}

$group = $app['db']->fetchAssoc('select *
	from ' . $s_schema . '.letsgroups
	where id = ?', [$group_id]);

if (!$group)
{
	echo 'InterSysteem niet gevonden.';
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	echo 'De apimethod voor dit interSysteem is niet elassoap.';
	exit;
}

if (!$group['remoteapikey'])
{
	echo 'De Remote Apikey is niet ingesteld voor dit interSysteem.';
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
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err, ['schema' => $tschema]);
	exit;
}

$message = $client->call('getstatus', ['apikey' => $apikey]);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen status verkrijgen. ' . $err;
	echo $m;
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err, ['schema' => $tschema]);
	exit;
}

echo $message;
exit;
