<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

echo "<h1>Instellingen</h1>";
$prefs = get_prefs();
show_preflisting();
show_prefs($prefs);

function show_preflisting(){
	$offset = date("O");
	echo "<p>Tijdzone: UTC$offset</p>";
}

function show_prefs($prefs){
	global $rootpath;
	echo "<div class='border_b'>";
        echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
        echo "<tr class='header'>";
	echo "<td nowrap valign='top'><strong>Categorie</strong></td>";
        echo "<td nowrap valign='top'><strong>Instelling</strong></td>";
	echo "<td nowrap valign='top'><strong>Waarde</strong></td>";
	echo "<td nowrap valign='top'><strong>Omschrijving</strong></td>";
        echo "</tr>";

        foreach($prefs as $key => $value){
                $rownumb=$rownumb+1;
                if($rownumb % 2 == 1){
                        echo "<tr class='uneven_row'>";
                }else{
                        echo "<tr class='even_row'>";
                }
                echo "<td nowrap valign='top'>";
                echo $value["category"];
                echo "</td>";
	        echo "<td nowrap valign='top'>";
		$mysetting = $value["setting"];
		echo "<a href='" . $rootpath . "preferences/editconfig.php?setting=$mysetting' >$mysetting</a>";
        echo "</td>";

		if($value["default"] == 't'){
			echo "<td nowrap valign='top' bgcolor='red'>";
		} else {
			echo "<td nowrap valign='top'>";
		}

                echo $value["value"];
                echo "</td>";
                echo "<td wrap valign='top'>";
                echo $value["description"];
                echo "</td>";
                echo "</tr>";
        }
        echo "</table></div>";
	echo "<P>Waardes in het rood moeten nog gewijzigd (of bevestigd) worden</P>";
	//echo "</table>";
}

function get_prefs(){
	global $db;
	$query = "SELECT * FROM config ORDER BY category,setting";
	$prefs = $db->GetArray($query);
	return $prefs;
}

include($rootpath."includes/inc_footer.php");
