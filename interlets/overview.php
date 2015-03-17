<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$groups = $db->GetArray('SELECT * FROM letsgroups');

include($rootpath."includes/inc_header.php");

echo "<table width='100%' border=0><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
echo '<li><a href="edit.php?mode=new">Groep toevoegen</a></li>';
echo '<li><a href="renderqueue.php">Interlets Queue</a></li>';
echo "</ul>";
echo "</div>";
echo "</td></tr></table>";

echo "<h1>Overzicht LETS groepen</h1>";

echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td valign='top'><strong>";
echo "ID";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Groepnaam";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "API";
echo "</strong></td>";
echo "</tr>\n\n";

foreach($groups as $value){
	$rownumb++;
	echo "<tr";
	if($rownumb % 2 == 1){
		echo " class='uneven_row'";
	}else{
			echo " class='even_row'";
	}
	echo ">";

	echo "<td><a href='view.php?id=" .$value['id'] ."'>" .$value['id'] ."</a></td>";
	echo "<td><a href='view.php?id=" .$value['id'] ."'>" .$value['groupname'] ."</a></td>";
	echo "<td>" .$value['apimethod'] ."</td>";
}

echo "</tr>";
echo "</table>";

echo "<p><small><i>";
echo "Belangrijk: er moet zeker een interletsrekening bestaan van het type internal om eLAS toe te laten met zichzelf te communiceren.  Deze moet een geldige SOAP URL en Apikey hebben.";
echo "</i></small></p>";

include($rootpath."includes/inc_footer.php");
