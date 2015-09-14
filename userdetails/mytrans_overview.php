<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$currency = readconfigfromdb('currency');

$user = readuser($s_id);

$interletsq = $db->fetchAll('select q.*, l.groupname
	from interletsq q, letsgroups l
	where q.id_from = ?
		and q.letsgroup_id = l.id', array($s_id));

$transactions = $db->fetchAll('select t.*,
		fu.name as from_username,
		tu.name as to_username,
		fu.letscode as from_letscode,
		tu.letscode as to_letscode
	from transactions t, users fu, users tu
	where (t.id_to = ?
		or t.id_from = ?)
		and t.id_to = tu.id
		and t.id_from = fu.id', array($s_id, $s_id));

$top_buttons = '<a href="' .$rootpath . 'transactions/add.php" class="btn btn-success"';
$top_buttons .= ' title="Nieuwe transactie toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn gegevens"><i class="fa fa-user"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn gegevens</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mymsg_overview.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'transactions/alltrans.php" class="btn btn-default"';
$top_buttons .= ' title="Alle transacties"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Alle transacties</span></a>';

$h1 = 'Mijn transacties';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

echo '<div>';
echo '<p><strong>' . $user['letscode'] .' '. $user['name']. ' huidige ';
echo $currency . ' stand: '.$user['saldo'].'</strong> || ';
echo '<strong>Limiet minstand: ' . $user['minlimit'] . '</strong></p>';
echo '</div>';

if(!empty($interletsq))
{
	echo '<h2>Interlets transacties in verwerking</h2>';

	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';

	echo '<thead>';
	echo '<tr>';
	echo '<th>Omschrijving</th>';
	echo '<th>Bedrag</th>';
	echo '<th data-hide="phone" data-sort-initial="descending">Tijdstip</th>';
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
}

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Omschrijving</th>';
echo '<th>Bedrag</th>';
echo '<th data-hide="phone" data-sort-initial="descending">Tijdstip</th>';
echo '<th data-hide="phone, tablet">Uit/In</th>';
echo '<th data-hide="phone, tablet">Tegenpartij</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($transactions as $t){

	echo '<tr>';
	echo '<td>';
	echo '<a href="' . $rootpath . 'transactions/view.php?id=' . $t['id'] . '">';
	echo htmlspecialchars($t['description'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';
	
	echo '<td>';
	echo '<span class="text-';
	echo ($t['id_from'] == $s_id) ? 'danger">-' : 'success">';
	echo $t['amount'];
	echo '</span></td>';

	echo '<td>';
	echo $t['cdate'];
	echo '</td>';

	echo '<td>';
	echo ($t['id_from'] == $s_id) ? 'Uit' : 'In'; 
	echo '</td>';

	if ($t['id_from'] == $s_id)
	{
		if ($t['real_to'])
		{
			$other_user = htmlspecialchars($t['real_to'], ENT_QUOTES);
		}
		else
		{
			$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_to'] . '">';
			$other_user .= htmlspecialchars($t['to_letscode'] . ' ' . $t['to_username'], ENT_QUOTES);
			$other_user .= '</a>';
		}
	}
	else
	{
		if ($t['real_from'])
		{
			$other_user = htmlspecialchars($t['real_from'], ENT_QUOTES);
		}
		else
		{
			$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_from'] . '">';
			$other_user .= htmlspecialchars($t['from_letscode'] . ' ' . $t['from_username'], ENT_QUOTES);
			$other_user .= '</a>';
		}
	}

	echo '<td>';
	echo $other_user;
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
