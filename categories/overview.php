<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$cats = $db->GetArray('SELECT * FROM categories ORDER BY fullname');

$child_count_ary = array();

foreach ($cats as $cat)
{
	$child_count_ary[$cat['id_parent']]++;
}

include($rootpath."includes/inc_header.php");
echo "<div class='border_b'>| <a href='add.php'>Categorie toevoegen</a> |</div>";
echo "<h1>Overzicht categorie&#235;n</h1>";

echo "<div class='border_b'>";
echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td><strong>Categorie</strong></td>";
echo '<td>Vraag</td>';
echo '<td>Aanbod</td>';
echo '<td>Aanpassen</td>';
echo '<td>Verwijderen</td>';
echo "</tr>";

foreach($cats as $value){

	if ($value["id_parent"] == 0)
	{
		echo "<tr class='even_row'>";
		echo "<td valign='top'><strong><a href='edit.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES);
		echo "</a></strong></td>";
	}
	else
	{
		echo "<tr class='uneven_row'>";
		echo "<td valign='top'><a href='edit.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES);
		echo "</a></td>";
	}

	echo '<td>' . (($v = $value['stat_msgs_wanted']) ? $v : '') . '</td>';
	echo '<td>' . (($v = $value['stat_msgs_offers']) ? $v : '') . '</td>';
	$child_count = $value['stat_msgs_wanted'] + $value['stat_msgs_offers'];
	$child_count += $child_count_ary[$value['id']];
	echo '<td><a href="edit.php?id=' . $value['id'] . '">Aanpassen</a></td>';
	echo '<td>' . (($child_count) ? '' : '<a href="delete.php?id=' . $value['id'] . '">Verwijderen</a>') . '</td>';
	echo "</tr>";
}
echo "</table></div>";

echo '<p>Categoriën met berichten of hoofdcategoriën met subcategoriën kan je niet verwijderen.</p>';

include($rootpath."includes/inc_footer.php");

