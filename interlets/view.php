<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

if (!isset($_GET['id']))
{
	header('Location: ' . $rootpath . 'interlets/overview.php');
}

$id = $_GET['id'];

$group = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($id));

$top_buttons = '<a href="' . $rootpath . 'interlets/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Letsgroep toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'interlets/edit.php?mode=edit&id=' . $id . '" class="btn btn-primary"';
$top_buttons .= ' title="Letsgroep aanpassen"><i class="fa fa-pencil"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'interlets/delete.php?id=' . $id . '" class="btn btn-danger"';
$top_buttons .= ' title="Letsgroep verwijderen">';
$top_buttons .= '<i class="fa fa-times"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'interlets/overview.php" class="btn btn-default"';
$top_buttons .= ' title="Lijst letsgroepen"><i class="fa fa-share-alt"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

$h1 = $group['groupname'];
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading">';

echo '<dl class="dl-horizontal">';
echo "<dt>eLAS Soap status</dt>";

echo "<dd><i><div id='statusdiv'>";

$soapurl = $group["elassoapurl"] ."/wsdlelas.php?wsdl";
$apikey = $group["remoteapikey"];
$client = new nusoap_client($soapurl, true);
$err = $client->getError();
if (!$err) {
	$result = $client->call('getstatus', array('apikey' => $apikey));
	$err = $client->getError();
    	if (!$err) {
		echo $result;
	}
}
echo "</div></i>";
echo "</dd>";

echo "<dt>Groepnaam</dt>";
echo "<dd>" .$group["groupname"] ."</dd>";

echo "<dt>Korte naam</dt>";
echo "<dd>" .$group["shortname"] ."</dd>";

echo "<dt>Prefix</dt>";
echo "<dd>" .$group["prefix"] ."</dd>";

echo "<dt>API methode</dt>";
echo "<dd>" .$group["apimethod"] ."</dd>";

echo "<dt>API key</dt>";
echo "<dd>" .$group["remoteapikey"] ."</dd>";

echo "<dt>Lokale LETS code</dt>";
echo "<dd>" .$group["localletscode"] ."</dd>";

echo "<dt>Remote LETS code</dt>";
echo "<dd>" .$group["myremoteletscode"] ."</dd>";

echo "<dt>URL</dt>";
echo "<dd>" .$group["url"] ."</dd>";

echo "<dt>SOAP URL</dt>";
echo "<dd>" .$group["elassoapurl"] ."</dd>";

echo "<dt>Preshared Key</dt>";
echo "<dd>" .$group["presharedkey"]."</dd>";
echo "</dl>";

echo '</div></div>';

echo "<p><small><i>";
echo "* API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail (internal is niet van tel in eLAS-Heroku)";
echo "<br>* De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten";
echo "<br>* Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan";
echo "<br>* Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn";
echo "<br>* URL is de weblocatie van de andere installatie";
echo "<br>* SOAP URL is de locatie voor de communicatie tussen eLAS en het andere systeem, voor een andere eLAS is dat de URL met /soap erachter";
echo "<br>* Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant";
echo "</i></small></p>";

include $rootpath . 'includes/inc_footer.php';
