<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$groups = $db->fetchAll('SELECT * FROM letsgroups');

$top_buttons = '<a href="' . $rootpath . 'interlets/edit.php?mode=add" class="btn btn-success"';
$top_buttons .= ' title="Groep toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'interlets/queue.php" class="btn btn-default"';
$top_buttons .= ' title="Interlets transactie queue"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Interlets queue</span></a>';

$h1 = 'LETS groepen';
$fa = 'share-alt';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-default">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th data-sort-initial="true">id</th>';
echo '<th>groepsnaam</th>';
echo '<th data-hide="phone">api</th>';
echo '<th data-hide="phone">leden</th>';
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
echo '</table>';
echo '</div></div>';

echo "<p><small><i>";
echo 'Attentie: In eLAS-Heroku is het niet langer nodig een \'internal\' groep aan te maken zoals dat in eLAS het geval is.';
echo "</i></small></p>";

include $rootpath . 'includes/inc_footer.php';
