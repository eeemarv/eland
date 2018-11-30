<?php
$rootpath = '../';
$page_access = 'guest';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$group_id = $_GET['group_id'] ?? false;

header('Content-type: application/json');

if (!$s_schema || $s_elas_guest)
{
	echo json_encode(['error' => 'Onvoldoende rechten.']);
	exit;
}

if (!$group_id)
{
	echo json_encode(['error' => 'Het interSysteem id ontbreekt.']);
	exit;
}

if (!isset($elas_interlets_groups[$group_id]))
{
	echo json_encode(['error' => 'Er is geen interSysteem verbinding met dit Systeem.']);
	exit;
}

$group = $app['db']->fetchAssoc('select *
	from ' . $s_schema . '.letsgroups
	where id = ?', [$group_id]);

if (!$group)
{
	echo json_encode(['error' => 'InterSysteem niet gevonden.']);
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	echo json_encode(['error' => 'De Api Methode voor dit interSysteem is niet elassoap.']);
	exit;
}

if (!$group['remoteapikey'])
{
	echo json_encode(['error' => 'De Remote Apikey is niet ingesteld voor dit interSysteem.']);
	exit;
}

if (!$group['presharedkey'])
{
	echo json_encode(['error' => 'De Preshared Key is niet ingesteld voor dit interSysteem.']);
	exit;
}

$soapurl = $group['elassoapurl'] ? $group['elassoapurl'] : $group['url'] . '/soap';
$soapurl = $soapurl . '/wsdlelas.php?wsdl';

$apikey = $group['remoteapikey'];

$client = new nusoap_client($soapurl, true);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen verbinding maken.';
	echo json_encode(['error' => $m]);
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err, ['schema' => $tschema]);
	exit;
}

$token = $client->call('gettoken', ['apikey' => $apikey]);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen token krijgen voor dit interSysteem.';
	echo json_encode(['error' => $m]);
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err, ['schema' => $tschema]);
	exit;
}

echo json_encode([
	'login_url'	=> $group['url'] . '/login.php?token=' . $token,
]);
exit;
