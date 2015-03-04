<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

show_ptitle();
show_body();

//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Waarschuwing over het gebruik van Internet Explorer 6</h1>";
}

function show_body(){
	echo "<P>eLAS is een internet applicatie die gebruikt maakt van moderne technieken (zoals CSS en AJAX).  Helaas werken veel van deze zaken niet of niet goed in Internet Explorer 6 of ouder omdat dit programma bestaande internet standaarden negeert (en dus in essentie defect is).</p>";

	echo "<P>Vanaf eLAS versie 2.0 wordt er dan ook geen poging meer gedaan om een gebroken product te ondersteunen ten koste van ander werk.  Om zeker te zijn van de goede werking van eLAS raden we je dan ook aan om over te schakelen op een nieuwere browser.</P>";

	echo "<P>eLAS werkt het beste met Mozilla <a href='http://www.firefox.com'>FireFox</a>, een veilig en gratis alternatief voor Internet Explorer dat zich aan gepubliceerde standaarden houdt.  Als je niet wenst over te schakelen naar een beter alternatief kan je ook Internet Explorer upgraden naar versie 7 of later (hoewel deze versies ook nog grote defecten vertonen).</P>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
