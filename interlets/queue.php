<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$h1 = 'Interlets queue';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

$interletsq = $db->GetArray("SELECT * FROM interletsq");

$interletsq = $db->GetArray('select q.*, l.groupname, u.letscode, u.fullname
	from interletsq q, letsgroups l, users u
	where q.id_from = ' . $s_id . '
		and q.letsgroup_id = l.id
		and q.id_from = u.id');

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Omschrijving</th>';
echo '<th>Bedrag</th>';
echo '<th data-hide="phone" data-sort-initial="descending">Tijdstip</th>';
echo '<th data-hide="phone, tablet">Van</th>';
echo '<th data-hide="phone, tablet">Aan letscode</th>';
echo '<th data-hide="phone, tablet">Groep</th>';
echo '<th data-hide="phone, tablet">Pogingen</th>';
echo '<th data-hide="phone, tablet">Status</th>';
echo '<th data-hide="phone, tablet">trans id</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($interletsq as $q)
{
	echo '<tr>';

	echo '<td>';
	echo htmlspecialchars($q['description'], ENT_QUOTES);
	echo '</td>';

	echo '<td>';
	echo $q['amount'] * readconfigfromdb('currencyratio');
	echo '</td>';

	echo '<td>';
	echo $q['date_created'];
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' . $q['from_id'] . '">';
	echo $q['letscode'] . ' ' . htmlspecialchars($q['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $q['letscode_to'];
	echo '</td>';

	echo '<td>';
	echo $q['groupname'];
	echo '</td>';

	echo '<td>';
	echo $q['retry_count'];
	echo '</td>';

	echo '<td>';
	echo $q['last_status'];
	echo '</td>';

	echo '<td>';
	echo $q['transid'];
	echo '</td>';
	
	echo '</tr>';

}
echo '</table></div>';

include $rootpath . 'includes/inc_footer.php';
