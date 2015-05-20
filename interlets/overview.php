<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$groups = $db->GetArray('SELECT * FROM letsgroups');

include($rootpath."includes/inc_header.php");

echo "<table><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
echo '<li><a href="edit.php?mode=new">Groep toevoegen</a></li>';
echo '<li><a href="renderqueue.php">Interlets Queue</a></li>';
echo "</ul>";
echo "</div>";
echo "</td></tr></table>";

echo "<h1>LETS groepen</h1>";

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th data-sort-initial="true">id</th>';
echo '<th>groepsnaam</th>';
echo '<th data-hide="phone">api</th>';
echo '<th data-hide="phone">actieve gebruikers</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($groups as $value)
{
	echo '<tr>';
	echo "<td><a href='view.php?id=" .$value['id'] ."'>" .$value['id'] ."</a></td>";
	echo "<td><a href='view.php?id=" .$value['id'] ."'>" .$value['groupname'] ."</a></td>";
	echo "<td>" .$value['apimethod'] ."</td>";
	echo '<td>' . $redis->get($value['url'] . '_active_user_count') . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo "</table>";
echo '</div>';

echo "<p><small><i>";
echo "Belangrijk: er moet zeker een interletsrekening bestaan van het type internal om eLAS toe te laten met zichzelf te communiceren.  Deze moet een geldige SOAP URL en Apikey hebben.";
echo "</i></small></p>";

include($rootpath."includes/inc_footer.php");
