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
echo "<div>| <a href='add.php'>Categorie toevoegen</a> |</div>";
echo "<h1>Overzicht categorie&#235;n</h1>";

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Categorie</th>';
echo '<th data-hide="phone, tablet">Vraag</th>';
echo '<th data-hide="phone, tablet">Aanbod</th>';
echo '<th data-hide="phone">Aanpassen</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '</tr></thead>';

foreach($cats as $value){

	if (!$value["id_parent"])
	{
		echo '<tr class="info">';
		echo "<td><strong><a href='edit.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["name"],ENT_QUOTES);
		echo "</a></strong></td>";
	}
	else
	{
		echo '<tr>';
		echo '<td>';
		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		echo '<a href="edit.php?id=' . $value['id'] . '">';
		echo htmlspecialchars($value["name"],ENT_QUOTES);
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

echo '<p>Categoriën met berichten of hoofdcategorieën met subcategorieën kan je niet verwijderen.</p>';

include($rootpath."includes/inc_footer.php");

