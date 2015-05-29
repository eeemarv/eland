<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$config = $db->GetArray('SELECT * FROM config ORDER BY category,setting');

$h1 = 'Instellingen';

include $rootpath . 'includes/inc_header.php';

echo 'Tijdzone: UTC' . date('O') . '</p>';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th>Categorie</th>';
echo '<th>Instelling</th>';
echo '<th>Waarde</th>';
echo '<th data-hide="phone">Omschrijving</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($config as $c)
{
	echo '<tr';
	echo ($c['default'] == 't') ? ' class="bg-danger"' : '';
	echo '>';
	echo '<td>' . $c['category'] . '</td>';
	echo '<td>';
	echo '<a href="' . $rootpath . 'preferences/edit.php?setting=' . $c['setting'] . '">';
	echo  $c['setting'] . '</a></td>';
	echo '<td>' . $c['value'] . '</td>';
	echo '<td>' . $c['description'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '<p>Waardes in het rood moeten nog gewijzigd (of bevestigd) worden</p>';

echo '</div></div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
