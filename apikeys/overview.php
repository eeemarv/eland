<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php");

include($rootpath."includes/inc_header.php");

echo "<table width='100%' border=0><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
echo "<li><a href='add.php'>Apikey toevoegen</a></li>";
echo "</div>";
echo "</td></tr></table>";
echo "<h1>Overzicht API Keys</h1>";
$apikeys= get_apikeys();
show_keys($apikeys);

include($rootpath."includes/inc_footer.php");


/////////////////


function showlinks($rootpath){
	global $s_id;

}

function show_keys($apikeys){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "ID";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Apikey";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
    echo "Creatiedatum";
    echo "</strong></td>";
    echo "<td valign='top'><strong>";
	echo "Type";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Comment";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
        echo "Verwijderen";
        echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;
	foreach($apikeys as $key => $value){
		$rownumb=$rownumb+1;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        	echo " class='even_row'";
		}
		echo ">";

		echo "<td>" .$value['id'] ."</td>";
		echo "<td>" .$value['apikey'] ."</td>";
		echo "<td>" .$value['created'] ."</td>";
		echo "<td>" .$value['type'] ."</td>";
		echo "<td>" .$value['comment'] ."</td>";
		echo "<td> | ";
		echo '<a href="delete.php?id=' .$value['id'] . '">';
		echo "Verwijderen |</a></td>";
	}
	echo "</tr>";
	echo "</table>";

}
