<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

include($rootpath."includes/inc_header.php");

if (!isset($_GET["id"])){
	header('Location: overview.php');
}

$id = $_GET["id"];
$group = $db->GetRow('SELECT * FROM letsgroups WHERE id = ' . $id);
echo '<h1>' . $group['groupname'] . '</h1>';
echo "<table width='95%' border='1'>";
echo "<tr>";
echo "<td>";

echo "<div >";
echo "<table width='95%' border='0'>";

echo "<tr>";
echo "<td>Groepnaam</td>";
echo "<td>" .$group["groupname"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Korte naam</td>";
echo "<td>" .$group["shortname"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Prefix</td>";
echo "<td>" .$group["prefix"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>API methode</td>";
echo "<td>" .$group["apimethod"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>API key</td>";
echo "<td>" .$group["remoteapikey"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Lokale LETS code</td>";
echo "<td>" .$group["localletscode"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Remote LETS code</td>";
echo "<td>" .$group["myremoteletscode"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>URL</td>";
echo "<td>" .$group["url"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>SOAP URL</td>";
echo "<td>" .$group["elassoapurl"] ."</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Preshared Key</td>";
echo "<td>" .$group["presharedkey"]."</td>";
echo "</tr>";

echo "</table>";
echo "</div>";


echo "</td>";

echo "<td valign='top' width='300'>";

//echo "<script type='text/javascript' src='/js/soapstatus.js'></script>";
echo "<table width='100%' border='0'>";

echo "<tr>";
echo "<td bgcolor='grey'>eLAS Soap status</td>";
echo "</tr>";
echo "<tr>";
echo "<td><i><div id='statusdiv'>";
//echo "<script type='text/javascript'>showsmallloader('statusdiv')</script>";
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
echo "</td>";
echo "</tr>";

echo "</table>";
//echo "<script type='text/javascript'>soapstatus($id)</script>";

echo "</td>";

echo "</tr>";
echo "</table>";

echo "<p><small><i>";
echo "* API methode bepaalt de connectie naar de andere groep, geldige waarden zijn internal, elassoap en mail";
echo "<br>* De API key moet je aanvragen bij de beheerder van de andere installatie, het is een sleutel die je eigen eLAS toelaat om met de andere eLAS te praten";
echo "<br>* Lokale LETS Code is de letscode waarmee de andere groep op deze installatie bekend is, deze gebruiker moet al bestaan";
echo "<br>* Remote LETS code is de letscode waarmee deze installatie bij de andere groep bekend is, deze moet aan de andere kant aangemaakt zijn";
echo "<br>* URL is de weblocatie van de andere installatie";
echo "<br>* SOAP URL is de locatie voor de communicatie tussen eLAS en het andere systeem, voor een andere eLAS is dat de URL met /soap erachter";
echo "<br>* Preshared Key is een gedeelde sleutel waarmee interlets transacties ondertekend worden.  Deze moet identiek zijn aan de preshared key voor de lets-rekening van deze installatie aan de andere kant";
echo "</i></small></p>";

echo "<table width='100%' border=0><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
echo '<li><a href="edit.php?mode=edit&id=' . $id . '">Aanpassen</a></li>';
echo '<li><a href="delete.php?id=' . $id . '">Verwijderen</a></li>';
echo "</ul>";
echo "</div>";
echo "</td></tr></table>";

include($rootpath."includes/inc_footer.php");
