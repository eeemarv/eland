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

echo "<div class='border_b'>";
echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";

echo "<td nowrap width='20%'><strong>Agendadatum</strong></td>";
echo "<td nowrap><strong>Titel</strong></td>";
echo ($s_accountrole == 'admin') ? '<td>Goedgekeurd</td>' : '';
echo "</tr>";
$rownumb = 0;
foreach($newsitems as $value)
{
	$rownumb++;
	if($rownumb % 2 == 1)
	{
		echo "<tr class='uneven_row'>";
	}else{
		echo "<tr class='even_row'>";
	}

	echo "<td nowrap valign='top'>";
	if(trim($value["itemdate"]) != "00/00/00"){
		list($date) = explode(' ', $value['itemdate']);
		echo $date;
	}
	echo "</td>";
	echo "<td valign='top'>";
	echo "<a href='view.php?id=".$value["id"]."'>";
	echo htmlspecialchars($value["headline"],ENT_QUOTES);
	echo "</a>";
	echo "</td>";
	if ($s_accountrole == 'admin')
	{
		echo '<td>';
		echo ($value['approved'] == 't') ? 'Goedgekeurd' : 'Nee';
		echo '</td>';
	}
	echo "</tr>";
}
echo "</table></div>";

include($rootpath."includes/inc_footer.php");
