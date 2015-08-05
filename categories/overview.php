<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$cats = $db->GetArray('SELECT * FROM categories ORDER BY fullname');

$child_count_ary = array();

foreach ($cats as $cat)
{
	$child_count_ary[$cat['id_parent']]++;
}

$top_buttons = '<a href="' . $rootpath . 'categories/add.php" class="btn btn-success"';
$top_buttons .= ' title="Categorie toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Categorieën';
$fa = 'files-o';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Categorie</th>';
echo '<th data-hide="phone">Vraag</th>';
echo '<th data-hide="phone">Aanbod</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($cats as $cat)
{
	$count_wanted = $cat['stat_msgs_wanted'];
	$count_offers = $cat['stat_msgs_offers'];
	$count = $count_wanted + $count_offers;
	$count += $child_count_ary[$cat['id']];

	if (!$cat['id_parent'])
	{
		echo '<tr class="info">';
		echo '<td><strong><a href="edit.php?id=' . $cat['id'] . '">';
		echo htmlspecialchars($cat['name'],ENT_QUOTES);
		echo '</a></strong></td>';
	}
	else
	{
		echo '<tr>';
		echo '<td>';
		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		echo '<a href="edit.php?id=' . $cat['id'] . '">';
		echo htmlspecialchars($cat['name'],ENT_QUOTES);
		echo '</a></td>';
	}

	echo '<td>' . (($count_wanted) ?: '') . '</td>';
	echo '<td>' . (($count_offers) ?: '') . '</td>';

	echo '<td>';
	if (!$count)
	{
		echo '<a href="delete.php?id=' . $cat['id'] . '" class="btn btn-danger btn-xs">';
		echo '<i class="fa fa-times"></i> Verwijderen</a>';
	}
	echo '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

echo '<p>Categorieën met berichten of hoofdcategorieën met subcategorieën kan je niet verwijderen.</p>';

include $rootpath . 'includes/inc_footer.php';

