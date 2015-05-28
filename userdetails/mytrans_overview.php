<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_userinfo.php';

$currency = readconfigfromdb('currency');

$user = readuser($s_id);

$interletsq = $db->GetArray('SELECT * FROM interletsq WHERE id_from = ' .$s_id);

$transactions = $db->GetArray('select t.*,
		fu.name as from_username,
		tu.name as to_username,
		fu.letscode as from_letscode,
		tu.letscode as to_letscode
	from transactions t, users fu, users tu
	where (t.id_to = ' . $s_id . '
		or t.id_from = ' . $s_id . ')
		and t.id_to = tu.id
		and t.id_from = fu.id');

$top_buttons = '<a href="' .$rootpath . 'transactions/add.php" class="btn btn-success"';
$top_buttons .= ' title="Nieuwe transactie toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Mijn transacties';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

echo '<div>';
echo '<p><strong>' . $user['letscode'] .' '. $user['name']. ' huidige ';
echo $currency . ' stand: '.$user['saldo'].'</strong> || ';
echo '<strong>Limiet minstand: ' . $user['minlimit'] . '</strong></p>';
echo '</div>';

	if(!empty($interletsq)){
			echo "<h2>Interlets transacties in verwerking</h2>";
			echo "<div class='border_b'>";
			echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
			echo "<tr class='header'>";
			//echo "<td valign='top'>TransID</td>";
			echo "<td valign='top'>Datum</td>";
			echo "<td valign='top'>Van</td>";
			echo "<td valign='top'>Groep</td>";
			echo "<td valign='top'>Aan</td>";
			echo "<td valign='top'>Waarde</td>";
			echo "<td valign='top'>Omschrijving</td>";
			echo "<td valign='top'>Pogingen</td>";
			echo "<td valign='top'>Status</td>";
			echo "</tr>";

			$rownumb=0;
			foreach($interletsq as $key => $value){
				$rownumb=$rownumb+1;
				if($rownumb % 2 == 1){
					echo "<tr class='uneven_row'>";
				}else{
					echo "<tr class='even_row'>";
				}
				echo "<td nowrap valign='top'>";
					echo $value["date_created"];
					echo "</td>";

				echo "<td nowrap valign='top'>";
			$user = get_user($value["id_from"]);
					//echo $value["id_from"];
			echo $user["fullname"];
					echo "</td>";

					echo "<td nowrap valign='top'>";
			$group = get_letsgroup($value["letsgroup_id"]);
			echo $group["shortname"];
					//echo $value["letsgroup_id"];
					echo "</td>";

			echo "<td nowrap valign='top'>";
			echo $value["letscode_to"];
			echo "</td>";

			echo "<td nowrap valign='top'>";
			$ratio = readconfigfromdb("currencyratio");
			$realvalue = $value["amount"] * $ratio;
			echo $realvalue;
			echo "</td>";

			echo "<td nowrap valign='top'>";
			echo $value["description"];
			echo "</td>";

			echo "<td nowrap valign='top'>";
			echo $value["retry_count"];
			echo "</td>";

			echo "<td nowrap valign='top'>";
			echo $value["last_status"];
			echo "</td>";

			echo "</tr>";
		}
		echo "</table></div>";
	}

	//my transactions

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

	// show transactions
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Datum</strong></td><td><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td>";
	echo "<td><strong>Bedrag uit</strong></td>";
	echo "<td><strong>Bedrag in</strong></td>";
	echo "<td><strong>Dienst</strong></td></tr>";
	$rownumb=0;
	foreach ($transactions as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		echo $value["datum"];
		echo '</td><td valign="top"';
		echo ($value['fromid'] == $s_id) ? ' class="me"' : '';
		echo '>';		
		if(!empty($value["real_from"])){
			echo htmlspecialchars($value["real_from"],ENT_QUOTES);
		} else {
			echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['fromid'] . '">';
			echo htmlspecialchars($value["fromname"],ENT_QUOTES)." (".trim($value["fromcode"]).")";
			echo '</a>';
		}
		echo '</td><td valign="top"';
		echo ($value['toid'] == $s_id) ? ' class="me"' : '';
		echo '>';
		if(!empty($value["real_to"])){
			echo htmlspecialchars($value["real_to"],ENT_QUOTES);
		} else {
			echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['toid'] . '">';
			echo htmlspecialchars($value["toname"],ENT_QUOTES)." (".trim($value["tocode"]).")";
			echo '</a>';
		}
		echo "</td>";

		if ($value["fromid"] == $s_id){
		 		echo "<td valign='top'>";
				echo "-".$value["amount"];
				echo "</td>";
				echo "<td></td>";
		}else{
			echo "<td></td>";
			echo "<td valign='top'>";
			echo "+".$value["amount"];
			echo "</td>";
		}
		echo "<td valign='top'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</td></tr>";
	}
	echo "</table></div>";	


////////////////////////////////////////////////////////////////////////////

include $rootpath . 'includes/inc_footer.php';
