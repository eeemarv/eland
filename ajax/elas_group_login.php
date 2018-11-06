<?php
$rootpath = '../';
$page_access = 'guest';
require_once __DIR__ . '/../include/web.php';

$group_id = $_GET['group_id'] ?? false;

header('Content-type: application/json');

if (!$s_schema || $s_elas_guest)
{
	echo json_encode(['error' => 'Onvoldoende rechten.']);
	exit;
}

if (!$group_id)
{
	echo json_encode(['error' => 'Het groep id ontbreekt.']);
	exit;
}

if (!isset($elas_interlets_groups[$group_id]))
{
	echo json_encode(['error' => 'Er is geen interletsconnectie met deze groep.']);
	exit;
}

$group = $app['db']->fetchAssoc('SELECT * FROM ' . $s_schema . '.letsgroups WHERE id = ?', [$group_id]);

if (!$group)
{
	echo json_encode(['error' => 'Groep niet gevonden.']);
	exit;
}

if ($group['apimethod'] != 'elassoap')
{
	echo json_encode(['error' => 'De apimethod voor deze groep is niet elassoap.']);
	exit;
}

if (!$group['remoteapikey'])
{
	echo json_encode(['error' => 'De remote apikey is niet ingesteld voor deze groep.']);
	exit;
}

if (!$group['presharedkey'])
{
	echo json_encode(['error' => 'De preshared key is niet ingesteld voor deze groep.']);
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
	echo json_encode(['error' => $m]);
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err);
	exit;
}

$token = $client->call('gettoken', ['apikey' => $apikey]);

$err = $client->getError();

if ($err)
{
	$m = $err_group . ' Kan geen token krijgen.';
	echo json_encode(['error' => $m]);
	$app['monolog']->error('elas-token: ' . $m . ' ' . $err);
	exit;
}

echo json_encode([
	'login_url'	=> $group['url'] . '/login.php?token=' . $token,
]);
exit;
