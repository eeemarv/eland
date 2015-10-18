<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_pagination.php';
require_once $rootpath . 'includes/inc_transactions.php';

$orderby = $_GET['orderby'];
$asc = $_GET['asc'];

$limit = ($_GET['limit']) ?: 25;
$start = ($_GET['start']) ?: 0;

$id = ($_GET['id']) ?: false;
$add = ($_GET['add']) ? true : false;
$mid = ($_GET['mid']) ?: false;
$tuid = ($_GET['tuid']) ?: false;
$fuid = ($_GET['fuid']) ?: false;
$uid = ($_GET['uid']) ?: false;
$inline = ($_GET['inline']) ? true : false;

$submit = ($_POST['zend']) ? true : false;

$currency = readconfigfromdb('currency');

if ($add)
{
	if ($s_guest)
	{
		$alert->error('Je hebt geen rechten om een transactie toe te voegen.');
		cancel();
	}

	$transaction = array();

	if ($submit)
	{
		$transaction['description'] = $_POST['description'];
		list($letscode_from) = explode(' ', $_POST['letscode_from']);
		list($letscode_to) = explode(' ', $_POST['letscode_to']);
		$transaction['amount'] = $_POST['amount'];
		$transaction['date'] = date('Y-m-d H:i:s');
		$letsgroup_id = $_POST['letsgroup_id'];

		$letsgroup = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

		if (!isset($letsgroup))
		{
			$alert->error('Letsgroep niet gevonden.');
		}

		if ($s_user)
		{
			$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE id = ?', array($s_id));
		}
		else
		{
			$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_from));
		}

		$letscode_touser = ($letsgroup['apimethod'] == 'internal') ? $letscode_to : $letsgroup['localletscode'];
		$touser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_touser));

		$transaction['id_from'] = $fromuser['id'];
		$transaction['id_to'] = $touser['id'];

		$transaction['transid'] = generate_transid();

		$errors = array();

		if (!$transaction['description'])
		{
			$errors[]= 'De omschrijving is niet ingevuld';
		}

		if (!$transaction['amount'])
		{
			$errors[] = 'Bedrag is niet ingevuld';
		}

		else if (!(ctype_digit($transaction['amount'])))
		{
			$errors[] = 'Het bedrag is geen geldig getal';
		}

		if(($fromuser['saldo'] - $transaction['amount']) < $fromuser['minlimit'] && !$s_admin)
		{
			$errors[] = 'Je beschikbaar saldo laat deze transactie niet toe';
		}

		if(empty($fromuser))
		{
			$errors[] = 'Gebruiker bestaat niet';
		}

		if(empty($touser) )
		{
			$errors[] = 'Bestemmeling bestaat niet';
		}

		if($fromuser['letscode'] == $touser['letscode'])
		{
			$errors[] = 'Van en Aan letscode zijn hetzelfde';
		}

		if($touser['saldo'] > $touser['maxlimit'] && !$s_admin)
		{
			$t_account = ($letsgroup['apimethod'] == 'internal') ? 'bestemmeling' : 'interletsrekening';
			$errors[] = 'De ' . $t_account . ' heeft zijn maximum limiet bereikt.';
		}

		if($letsgroup['apimethod'] == 'internal'
			&& !$s_admin
			&& !($touser['status'] == '1' || $touser['status'] == '2'))
		{
			$errors[] = 'De bestemmeling is niet actief';
		}

		if (!$transaction['date'])
		{
			$errors[] = 'Datum is niet ingevuld';
		}
		else if (strtotime($transaction['date']) == -1)
		{
			$errors[] = 'Fout in datumformaat (jjjj-mm-dd)';
		}

		if(!empty($errors))
		{
			$alert->error(implode('<br>', $errors));
		}
		else
		{
			switch($letsgroup['apimethod'])
			{
				case 'internal':
				case 'mail':

					if (insert_transaction($transaction))
					{
						if ($letsgroup['apimethod'] == 'internal')
						{
							mail_transaction($transaction);
						}
						else
						{
							mail_interlets_transaction($transaction);
						}
						$alert->success('Transactie opgeslagen');
					}
					else
					{
						$alert->error('Gefaalde transactie');
					}
					cancel();

					break;

				case 'elassoap':

					$transaction['letscode_to'] = $letscode_to;
					$transaction['letsgroup_id'] = $letsgroup_id;
					$currencyratio = readconfigfromdb('currencyratio');
					$transaction['amount'] = $transaction['amount'] / $currencyratio;
					$transaction['amount'] = (float) $transaction['amount'];
					$transaction['amount'] = round($transaction['amount'], 5);
					$transaction['signature'] = sign_transaction($transaction, $letsgroup['presharedkey']);
					$transaction['retry_until'] = gmdate('Y-m-d H:i:s', time() + 86400);

					$transid = queuetransaction($transaction, $fromuser, $touser);

					if($transaction['transid'] == $transid)
					{
						$alert->success('Interlets transactie in verwerking');
					}
					else
					{
						$alert->error('Gefaalde transactie');
					}

					cancel();

					break;

				default:

					$alert->error('Geen geldige apimethode geselecteerd voor deze letsgroep. (contacteer een admin)');

					break;
			}
		}

		$transaction['letscode_to'] = $_POST['letscode_to'];
		$transaction['letscode_from'] = ($s_admin) ? $_POST['letscode_from'] : $s_letscode . ' ' . $s_name;
	}
	else
	{
		$transaction = array(
			'date'			=> date('Y-m-d'),
			'letscode_from'	=> $s_letscode . ' ' . $s_name,
			'letscode_to'	=> '',
			'amount'		=> '',
			'description'	=> '',
		);

		if ($mid)
		{
			$row = $db->fetchAssoc('SELECT
					m.content, m.amount, u.letscode, u.name
				FROM messages m, users u
				WHERE u.id = m.id_user
					AND m.id = ?', array($mid));
			$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['name'];
			$transaction['description'] =  '#m' . $mid . ' ' . $row['content'];
			$transaction['amount'] = $row['amount'];
		}
		else if ($tuid)
		{
			$row = readuser($tuid);
			$transaction['letscode_to'] = $row['letscode'] . ' ' . $row['name'];
		}

		if ($fuid && $s_admin)
		{
			$row = readuser($fuid);
			$transaction['letscode_from'] = $row['letscode'] . ' ' . $row['name'];
		}
	}

	$internal_letsgroup_prefixes = array();

	$rs = $db->prepare('SELECT id, prefix
		FROM letsgroups
		WHERE apimethod = \'internal\'
		ORDER BY prefix asc');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$internal_letsgroup_prefixes[$row['id']] = $row['prefix'];
	}

	foreach ($internal_letsgroup_prefixes as $letsgroup_id => $letsgroup_prefix)
	{
		if (!$letsgroup_prefix)
		{
			break;
		}

		if (strpos(strtolower($s_letscode), strtolower($letsgroup_prefix) === 0))
		{
			break;
		}
	}

	if (!isset($_POST['zend']))
	{
		$letsgroup['id'] = $letsgroup_id;
	}

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/transactions_add.js"></script>';

	$user = readuser($s_id);
	$balance = $user['saldo'];

	$letsgroups = $db->fetchAll('SELECT id, groupname, url FROM letsgroups');

	$currency = readconfigfromdb('currency');

	$top_buttons .= '<a href="' . $rootpath . 'transactions.php" class="btn btn-default"';
	$top_buttons .= ' title="Transactielijst"><i class="fa fa-exchange"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'transactions.php?uid=' . $s_id . '" class="btn btn-default"';
	$top_buttons .= ' title="Mijn transacties"><i class="fa fa-exchange"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';

	$h1 = 'Nieuwe transactie';
	$fa = 'exchange';

	include $rootpath . 'includes/inc_header.php';

	$minlimit = $user['minlimit'];

	echo '<div>';
	echo '<p><strong>' . link_user($user) . ' huidige ' . $currency . ' stand: ' . $balance . '</strong> || ';
	echo '<strong>Limiet minstand: ' . $minlimit . '</strong></p>';
	echo '</div>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" class="form-horizontal">';

	echo ($s_admin) ? '' : '<div style="display:none;">';

	echo '<div class="form-group"';
	echo ($s_admin) ? '' : ' disabled="disabled" ';
	echo '>';
	echo '<label for="letscode_from" class="col-sm-2 control-label">';
	echo '<span class="label label-default">Admin</span> ';
	echo 'Van letscode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="letscode_from" name="letscode_from" ';
	echo 'value="' . $transaction['letscode_from'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo ($s_admin) ? '' : '</div>';

	echo '<div class="form-group">';
	echo '<label for="letsgroup_id" class="col-sm-2 control-label">Aan letsgroep</label>';
	echo '<div class="col-sm-10">';
	echo '<select type="text" class="form-control" id="letsgroup_id" name="letsgroup_id">';
	foreach ($letsgroups as $l)
	{
		$thumbprint = (getenv('ELAS_DEBUG')) ? time() : $redis->get($l['url'] . '_typeahead_thumbprint');
		echo '<option value="' . $l['id'] . '" ';
		echo 'data-thumbprint="' . $thumbprint . '"';
		echo ($l['id'] == $letsgroup['id']) ? ' selected="selected"' : '';
		echo ($l['id'] == $letsgroup_id) ? ' data-this-letsgroup="1"' : '';
		echo '>' . htmlspecialchars($l['groupname'], ENT_QUOTES) . '</option>';
	}
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="letscode_to" class="col-sm-2 control-label">Aan letscode</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="letscode_to" name="letscode_to" ';
	echo 'value="' . $transaction['letscode_to'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="amount" class="col-sm-2 control-label">Aantal ' . $currency . '</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="number" class="form-control" id="amount" name="amount" ';
	echo 'value="' . $transaction['amount'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="description" name="description" ';
	echo 'value="' . $transaction['description'] . '" required>';
	echo '</div>';
	echo '</div>';

	echo '<a href="' . $rootpath . 'transactions.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Overschrijven" class="btn btn-success">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	echo '<small><p>Tip: Het veld Aan LETSCode geeft autosuggesties door naam of letscode in te typen. ';
	echo 'Kies eerst de juiste letsgroep om de juiste suggesties te krijgen.</p></small>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($id)
{
	$transaction = $db->fetchAssoc('select t.*
		from transactions t
		where t.id = ?', array($id));

	if ($s_user || $s_admin)
	{
		$top_buttons .= '<a href="' . $rootpath . 'transactions.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="Transactie toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
	}

	$top_buttons .= '<a href="' . $rootpath . 'transactions.php" class="btn btn-default"';
	$top_buttons .= ' title="Transactielijst"><i class="fa fa-exchange"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$h1 = 'Transactie';
	$fa = 'exchange';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';

	echo '<dl class="dl-horizontal">';
	echo '<dt>Tijdstip</dt>';
	echo '<dd>';
	echo $transaction['date'];
	echo '</dd>';

	echo '<dt>Creatietijdstip</dt>';
	echo '<dd>';
	echo $transaction['cdate'];
	echo '</dd>';

	echo '<dt>Transactie ID</dt>';
	echo '<dd>';
	echo $transaction['transid'];
	echo '</dd>';

	echo '<dt>Van account</dt>';
	echo '<dd>';
	echo link_user($transaction['id_from']);
	echo '</dd>';

	if ($transaction['real_from'])
	{
		echo '<dt>Van remote gebruiker</dt>';
		echo '<dd>';
		echo $transaction['real_from'];
		echo '</dd>';
	}

	echo '<dt>Naar account</dt>';
	echo '<dd>';
	echo link_user($transaction['id_to']);
	echo '</dd>';

	if ($transaction['real_to'])
	{
		echo '<dt>Naar remote gebruiker</dt>';
		echo '<dd>';
		echo $transaction['real_to'];
		echo '</dd>';
	}

	echo '<dt>Waarde</dt>';
	echo '<dd>';
	echo $transaction['amount'] . ' ' . $currency;
	echo '</dd>';

	echo '<dt>Omschrijving</dt>';
	echo '<dd>';
	echo $transaction['description'];
	echo '</dd>';

	echo '</dl>';

	echo '</div></div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$s_owner = ($s_id == $uid && $s_id && $uid) ? true : false;

$orderby = (isset($orderby) && ($orderby != '')) ? $orderby : 'cdate';
$asc = (isset($asc) && ($asc != '')) ? $asc : 0;

$query_orderby = ($orderby == 'fromusername' || $orderby == 'tousername') ? $orderby : 't.' . $orderby;
$where = ($uid) ? ' where t.id_from = ? or t.id_to = ? ' : '';
$sql_params = ($uid) ? array($uid, $uid) : array();
$query = 'select t.*
	from transactions t ' .
	$where . '
	order by ' . $query_orderby . ' ';
$query .= ($asc) ? 'ASC ' : 'DESC ';
$query .= ' LIMIT ' . $limit . ' OFFSET ' . $start;

$transactions = $db->fetchAll($query, $sql_params);

$row_count = $db->fetchColumn('select count(t.*)
	from transactions t ' . $where, $sql_params);

$filter = ($uid) ? '&uid=' . $uid : '';

$pagination = new pagination(array(
	'limit' 		=> $limit,
	'start' 		=> $start,
	'base_url' 		=> $rootpath . 'transactions.php?orderby=' . $orderby . '&asc=' . $asc . $filter,
	'row_count'		=> $row_count,
));

$asc_preset_ary = array(
	'asc'	=> 0,
	'indicator' => '',
);

$tableheader_ary = array(
	'description' => array_merge($asc_preset_ary, array(
		'lang' => 'Omschrijving')),
	'amount' => array_merge($asc_preset_ary, array(
		'lang' => 'Bedrag')),
	'cdate'	=> array_merge($asc_preset_ary, array(
		'lang' 		=> 'Tijdstip',
		'data_hide' => 'phone'))
);

if ($uid)
{
	$tableheader_ary['user'] = array_merge($asc_preset_ary, array(
		'lang'			=> 'Tegenpartij',
		'data_hide'		=> 'phone, tablet',
		'no_sort'		=> true,
	));
}
else
{
	$tableheader_ary += array(
		'from_user' => array_merge($asc_preset_ary, array(
			'lang' 		=> 'Van',
			'data_hide'	=> 'phone, tablet',
			'no_sort'	=> true,
		)),
		'to_user' => array_merge($asc_preset_ary, array(
			'lang' 		=> 'Aan',
			'data_hide'	=> 'phone, tablet',
			'no_sort'	=> true,
		)),
	);
}

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';

if ($s_admin || $s_user)
{
	if ($uid)
	{
		$user_str = link_user($uid, null, false);

		if ($s_admin)
		{
			$top_buttons .= '<a href="' . $rootpath . 'transactions.php?add=1&fuid=' . $uid . '" class="btn btn-success"';
			$top_buttons .= ' title="Transactie van ' . $user_str . '"><i class="fa fa-plus"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Transactie van ' . $user_str . '</span></a>';
		}

		if ($s_admin || ($s_user && !$s_owner))
		{
			$top_buttons .= '<a href="' . $rootpath . 'transactions.php?add=1&tuid=' . $uid . '" class="btn btn-success"';
			$top_buttons .= ' title="Transactie naar ' . $user_str . '"><i class="fa fa-plus"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Transactie naar ' . $user_str . '</span></a>';
		}

		if (!$inline)
		{
			$top_buttons .= '<a href="' . $rootpath . 'transactions.php" class="btn btn-default"';
			$top_buttons .= ' title="Lijst"><i class="fa fa-exchange"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';
		}
	}
	else
	{
		$top_buttons .= '<a href="' . $rootpath . 'transactions.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="Transactie toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

		$top_buttons .= '<a href="' . $rootpath . 'transactions.php?uid=' . $s_id . '" class="btn btn-default"';
		$top_buttons .= ' title="Mijn transacties"><i class="fa fa-user"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';
	}
}

if ($s_admin)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}


$h1 = 'Transacties';
$h1 .= ($uid) ? ' van ' . link_user($uid) : '';
$h1 = (!$s_admin && $s_owner) ? 'Mijn transacties' : $h1;
$fa = 'exchange';

if (!$inline)
{
	$includejs = '<script src="' . $rootpath . 'js/csv.js"></script>';

	include $rootpath . 'includes/inc_header.php';
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-exchange"></i> ' . $h1;
	echo '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

$pagination->render();

echo '<div class="panel panel-primary">';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable csv transactions" ';
echo 'data-sort="false">';
echo '<thead>';
echo '<tr>';

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';
	echo ($data['data_hide']) ? ' data-hide="' . $data['data_hide'] . '"' : '';
	echo '>';
	if ($data['no_sort'])
	{
		echo $data['lang'];
	}
	else
	{
		echo '<a href="' . $rootpath . 'transactions.php?orderby=' . $key_orderby . '&asc=' . $data['asc'] . $filter . '">';
		echo $data['lang'];
		echo '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>';
		echo '</a>';
	}
	echo '</th>';
}

echo '</tr>';
echo '</thead>';
echo '<tbody>';

if ($uid)
{
	foreach($transactions as $t)
	{
		echo '<tr>';
		echo '<td>';
		echo '<a href="' . $rootpath . 'transactions.php?id=' . $t['id'] . '">';
		echo htmlspecialchars($t['description'], ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo '<span class="text-';
		echo ($t['id_from'] == $uid) ? 'danger">-' : 'success">+';
		echo $t['amount'];
		echo '</span></td>';

		echo '<td>';
		echo $t['cdate'];
		echo '</td>';

		echo '<td>';

		if ($t['id_from'] == $uid)
		{
			if ($t['real_to'])
			{
				echo htmlspecialchars($t['real_to'], ENT_QUOTES);
			}
			else
			{
				echo link_user($t['id_to']);
			}
		}
		else
		{
			if ($t['real_from'])
			{
				echo htmlspecialchars($t['real_from'], ENT_QUOTES);
			}
			else
			{
				echo link_user($t['id_from']);
			}
		}

		echo '</td>';
		echo '</tr>';
	}
}
else
{
	foreach($transactions as $t)
	{
		echo '<tr>';
		echo '<td><a href="' . $rootpath . 'transactions.php?id=' . $t['id'] . '">';
		echo htmlspecialchars($t['description'],ENT_QUOTES);
		echo '</a>';
		echo '</td>';

		echo '<td>';
		echo $t['amount'];
		echo '</td>';

		echo '<td>';
		echo $t['cdate'];
		echo '</td>';

		echo '<td';
		echo ($t['id_from'] == $s_id) ? ' class="me"' : '';
		echo '>';
		if(!empty($t['real_from']))
		{
			echo htmlspecialchars($t['real_from'],ENT_QUOTES);
		}
		else
		{
			echo link_user($t['id_from']);
		}
		echo '</td>';

		echo '<td';
		echo ($t['id_to'] == $s_id) ? ' class="me"' : '';
		echo '>';
		if(!empty($t["real_to"]))
		{
			echo htmlspecialchars($t["real_to"],ENT_QUOTES);
		}
		else
		{ 
			echo link_user($t['id_to']);
		}
		echo '</td>';

		echo '</tr>';
	}
}
echo '</table></div></div>';

$pagination->render();

if ($inline)
{
	echo '</div></div>';
}
else
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $rootpath;

	header('Location: ' . $rootpath . 'transactions.php' . (($id) ? '?id=' . $id : ''));
	exit;
}
