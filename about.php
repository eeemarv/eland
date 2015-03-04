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

//include($rootpath."includes/inc_header.php");
//include($rootpath."includes/inc_nav.php");

show_ptitle();
show_body();

//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Over eLAS</h1>";
}

function show_body(){
	global $elas;
	$schemaversion = schema_check();
	$myhost = gethostname();
	echo "<P>eLAS is een webapplicatie voor het beheren van LETS groepen.<br>";
	echo "Het wordt uitgegeven onder de <a href='license.txt'>Affero General Public License (AGPL) versie 3</a> en is gebasseerd op <a href='http://marva.antwerpencentraal.be'>MARVA</a> 1.0-rc5.";
	echo "  De ontwikkeling en infrastructuur wordt ondersteund door <a href='http://www.taurix.net'>Taurix IT</a>.";
	echo "</p>";

	echo "<p>Voor meer informatie, bezoek de eLAS <a href='http://elas.vsbnet.be'>website</a>.</p>";
	echo "<p><strong>Auteurs</strong></p>";
	echo "Guy Van Sanden (Lets Geel)<br>";
	echo "</p>";

	echo "<p><strong>Bijdragen (patches):</strong></p>";
	echo "Dimitri D&#39;hondt (LETS Aalst - Oudenaarde): Regio zoekfuncties<br>";
    echo "Bernard Butaye (LETS Antwerpen Stad): XML V/A export<br>";
    echo "Ivo van den Maagdenberg (LETS Antwerpen Stad): Bugfixes and code<br>";
	echo "</p>";

	echo "<P>eLAS gebruikt volgend externe componenten:<small>";
	echo "<ul>";
	echo "<li><a href='http://mootools.net'>MooTools</a> AJAX framework onder de MIT public license</li>";
 	echo "<li><a href='http://mootools.net/forge/p/growler'>Growler</a> notifier</li>";
	echo "<li><a href='http://adodb.sourceforge.net'>AdoDB</a> libraries onder de LGPL om te connecteren met de database</li>";
	echo "<li><a href='http://sourceforge.net/projects/nusoap/'>NuSOAP</a> onder de LGPL voor web services</li>";
	echo "<li><a href='http://www.scriptiny.com/2011/03/javascript-modal-windows'>Tinybox</a> onder de Creative Commons</li>";
	echo "<li><a href='http://code.google.com/p/lightopenid>Google lightopenid</a> onder de MIT licentie</li>";
	echo "<li><a href='http://http://peej.github.com/tonic/'>Tonic</a> REST libraries</li>";
	echo "<li><a href='http://jodreports.sourceforge.net/'>Jooreports</a> onder de LGPL voor rapportgeneratie.</li>";
	echo "<li><a href='http://swiftmailer.org'>Swiftmailer</a> onder de LGPL</li>";
	echo "<li><a href='http://ckeditor.com'>CKEditor</a> under the LGPL</li>";
	echo "</ul></small>";
	echo "</P>";

	echo "<p><small>Build from branch: " . $elas->branch .", revision: " .$elas->revision .", build: " .$elas->build;
	echo "<br>Webserver: $myhost</small></p>\n";

}

function schema_check(){
        //echo $version;
        global $db;
        $query = "SELECT * FROM parameters WHERE parameter= 'schemaversion'";

        $result = $db->GetRow($query) ;
        return $result["value"];
}

//include($rootpath."includes/inc_sidebar.php");
//include($rootpath."includes/inc_footer.php");
?>
