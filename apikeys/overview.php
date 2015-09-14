<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$apikeys = $db->fetchAll('select * from apikeys');

$top_buttons = '<a href="' . $rootpath . 'apikeys/add.php" class="btn btn-success"';
$top_buttons .= ' title="Apikey toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Apikeys';
$fa = 'key';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th>Id</th>';
echo '<th>Comment</th>';
echo '<th data-hide="phone">Apikey</th>';
echo '<th data-hide="phone, tablet" data-sort-initial="true">Creatietijdstip</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($apikeys as $a)
{
	echo '<tr>';
	echo '<td>' . $a['id'] . '</td>';
	echo '<td>' . $a['comment'] . '</td>';
	echo '<td>' . $a['apikey'] . '</td>';
	echo '<td>' . $a['created'] . '</td>';
	echo '<td><a href="' . $rootpath . 'apikeys/delete.php?id=' . $a['id'] . '" class="btn btn-danger btn-xs">';
	echo '<i class="fa fa-times"></i> Verwijderen</a></td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';
echo '</div>';

include $rootpath.'includes/inc_footer.php';
