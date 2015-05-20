<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$query = 'SELECT * FROM news';

if($s_accountrole != "admin"){
	$query .= " AND approved = True";
}

$query .= " ORDER BY cdate DESC";
$newsitems = $db->GetArray($query);

include($rootpath."includes/inc_header.php");

if($s_accountrole == "user" || $s_accountrole == "admin"){
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<li><a href='edit.php?mode=new'>Toevoegen</a></li>";
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

echo "<h1>Nieuws</h1>";

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo "<th>Titel</th>";
echo '<th data-hide="phone" data-sort-initial="true">Agendadatum</th>';
echo ($s_accountrole == 'admin') ? '<th data-hide="phone, tablet">Goedgekeurd</th>' : '';
echo "</tr>";
echo '</thead>';

echo '<tbody>';
foreach ($newsitems as $value)
{
	echo '<tr>';

	echo "<td>";
	echo "<a href='view.php?id=".$value["id"]."'>";
	echo htmlspecialchars($value["headline"],ENT_QUOTES);
	echo "</a>";
	echo "</td>";

	echo '<td>';
	if(trim($value["itemdate"]) != "00/00/00")
	{
		list($date) = explode(' ', $value['itemdate']);
		echo $date;
	}
	echo "</td>";

	if ($s_accountrole == 'admin')
	{
		echo '<td>';
		echo ($value['approved'] == 't') ? 'Goedgekeurd' : 'Nee';
		echo '</td>';
	}
	echo "</tr>";
}
echo '</tbody>';
echo "</table></div>";

include($rootpath."includes/inc_footer.php");
