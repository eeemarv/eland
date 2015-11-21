<?php

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_transactions.php';

$q = ($_POST['q']) ?: (($_GET['q']) ?: '');
$hsh = ($_POST['hsh']) ?: (($_GET['hsh']) ?: '096024');
$selected_users = $_POST['selected_users'];
$selected_users = ltrim($selected_users, '.');
$selected_users = explode('.', $selected_users);
$selected_users = array_combine($selected_users, $selected_users);

$st = array(
	'active'	=> array(
		'lbl'	=> 'Actief',
		'st'	=> 1,
		'hsh'	=> '58d267',
	),
	'without-leaving-and-new' => array(
		'lbl'	=> 'Actief zonder uit- en instappers',
		'st'	=> '123',
		'hsh'	=> '096024',
	),
	'leaving'	=> array(
		'lbl'	=> 'Uitstappers',
		'st'	=> 2,
		'hsh'	=> 'ea4d04',
		'cl'	=> 'danger',
	),
	'new'		=> array(
		'lbl'	=> 'Instappers',
		'st'	=> 3,
		'hsh'	=> 'e25b92',
		'cl'	=> 'success',
	),
	'inactive'	=> array(
		'lbl'	=> 'Inactief',
		'st'	=> 0,
		'hsh'	=> '79a240',
		'cl'	=> 'inactive',
	),
	'info-packet'	=> array(
		'lbl'	=> 'Info-pakket',
		'st'	=> 5,
		'hsh'	=> '2ed157',
		'cl'	=> 'warning',
	),
	'info-moment'	=> array(
		'lbl'	=> 'Info-moment',
		'st'	=> 6,
		'hsh'	=> '065878',
		'cl'	=> 'info',
	),
	'all'		=> array(
		'lbl'	=> 'Alle',
	),
);

$status_ary = array(
	0 	=> 'inactive',
	1 	=> 'active',
	2 	=> 'leaving',
	3	=> 'new',
	5	=> 'info-packet',
	6	=> 'info-moment',
	7	=> 'extern',
	123 => 'without-leaving-and-new',
);

$currency = readconfigfromdb('currency');

$users = array();

$rs = $db->prepare(
	'SELECT id, name, letscode,
		accountrole, status, saldo,
		minlimit, maxlimit, adate,
		postcode
	FROM users
	WHERE status IN (0, 1, 2, 5, 6)
	ORDER BY letscode');

$rs->execute();

while ($row = $rs->fetch())
{
	$users[$row['id']] = $row;
}

list($to_letscode) = explode(' ', $_POST['to_letscode']);
list($from_letscode) = explode(' ', $_POST['from_letscode']);
$amount = $_POST['amount'] ?: array();
$description = $_POST['description'];
$password = $_POST['password'];
$transid = $_POST['transid'];
$mail_en = ($_POST['mail_en']) ? true : false;
$transid = $_POST['transid'];

if ($_POST['zend'])
{
	$errors = array();

	if (!$password)
	{
		$errors[] = 'Paswoord is niet ingevuld.';
	}
	else
	{
		$password = hash('sha512', $password);

		if ($password != $db->fetchColumn('select password from users where id = ?', array($s_id)))
		{
			$errors[] = 'Paswoord is niet juist.';
		}
	}

	if (!$description)
	{
		$errors[] = 'Vul een omschrijving in.';
	}

	if ($to_letscode && $from_letscode)
	{
		$errors[] = '\'Van letscode\' en \'Aan letscode\' kunnen niet beide ingevuld worden.';
	}
	else if (!($to_letscode || $from_letscode))
	{
		$errors[] = '\'Van letscode\' OF \'Aan letscode\' moet ingevuld worden.';
	}
	else
	{
		$to_one = ($to_letscode) ? true : false;
		$letscode = ($to_one) ? $to_letscode : $from_letscode;

		$one_uid = $db->fetchColumn('select id from users where letscode = ?', array($letscode));

		if (!$one_uid)
		{
			$field = ($to_one) ? '\'Aan letscode\'' : '\'Van letscode\'';
			$errors[] = 'Geen bestaande letscode in veld ' . $field . '.';
		}
		else
		{
			unset($amount[$one_uid]);
		}
	}

	$filter_options = array(
		'options'	=> array(
			'min_range' => 0,
		),
	);

	$count = 0;

	foreach ($amount as $uid => $amo)
	{
		if (!$selected_users[$uid])
		{
			continue;
		}

		if (!$amo)
		{
			continue;
		}

		$count++;

		if (!filter_var($amo, FILTER_VALIDATE_INT, $filter_options))
		{
			$errors[] = 'Ongeldig bedrag ingevuld.';
			break;
		}
	}

	if (!$count)
	{
		$errors[] = 'Er is geen enkel bedrag ingevuld.';
	}

	if (!$transid)
	{
		$errors[] = 'Geen geldig transactie id';
	}

	if ($db->fetchColumn('select id from transactions where transid = ?', array($transid)))
	{
		$errors[] = 'Een dubbele boeking van een transactie werd voorkomen.';
	}

	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else
	{
		$transactions = array();

		$db->beginTransaction();

		$date = date('Y-m-d H:i:s');
		$cdate = gmdate('Y-m-d H:i:s');

		$one_field = ($to_one) ? 'to' : 'from';
		$many_field = ($to_one) ? 'from' : 'to';

		$mail_ary = array(
			$one_field 		=> $one_uid,
			'description'	=> $description,
			'date'			=> $date,
		);

		$alert_success = $log = '';
		$total = 0;

		try
		{

			foreach ($amount as $many_uid => $amo)
			{
				if (!$selected_users[$many_uid])
				{
					continue;
				}

				if (!$amo || $many_uid == $one_uid)
				{
					continue;
				}

				$many_user = $users[$many_uid];
				$to_id = ($to_one) ? $one_uid : $many_uid;
				$from_id = ($to_one) ? $many_uid : $one_uid;
				$from_user = $users[$from_id];
				$to_user = $users[$to_id];

				$alert_success .= 'Transactie van gebruiker ' . $from_user['letscode'] . ' ' . $from_user['name'];
				$alert_success .= ' naar ' . $to_user['letscode'] . ' ' . $to_user['name'];
				$alert_success .= '  met bedrag ' . $amo .' ' . $currency . ' uitgevoerd.<br>';

				$log_many .= $many_user['letscode'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

				$mail_ary[$many_field][$many_uid] = array(
					'amount'	=> $amo,
					'transid' 	=> $transid,
				);

				$trans = array(
					'id_to' 		=> $to_id,
					'id_from' 		=> $from_id,
					'amount' 		=> $amo,
					'description' 	=> $description,
					'date' 			=> $date,
					'cdate' 		=> $cdate,
					'transid'		=> $transid,
					'creator'		=> $s_id,
				);

				$db->insert('transactions', $trans);

				$db->executeUpdate('update users
					set saldo = saldo ' . (($to_one) ? '- ' : '+ ') . '?
					where id = ?', array($amo, $many_uid));

				$total += $amo;

				$transid = generate_transid();

				$transactions[] = $trans;
			}

			$db->executeUpdate('update users
				set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . '?
				where id = ?', array($total, $one_uid));

			$db->commit();
		}
		catch (Exception $e)
		{
			$alert->error('Fout bij het opslaan.');
			$db->rollback();
			throw $e;
		}

		foreach($transactions as $t)
		{
			autominlimit_queue($t['id_from'], $t['id_to'], $t['amount']);
		}

		$alert_success .= 'Totaal: ' . $total . ' ' . $currency;
		$alert->success($alert_success);

		$log_one = $users[$one_uid]['letscode'] . ' ' . $users[$one_uid]['name'] . ' (Total amount: ' . $total . ' ' . $currency . ')'; 
		$log_many = rtrim($log_many, ', ');
		$log_str = 'Mass transaction from ';
		$log_str .= ($to_one) ? $log_many : $log_one;
		$log_str .= ' to ';
		$log_str .= ($to_one) ? $log_one : $log_many;

		log_event($s_id, 'Trans', $log_str);

		if ($mail_en)
		{
			if (mail_mass_transaction($mail_ary))
			{
				$alert->success('Notificatie mails verzonden.');
			}
			else
			{
				$alert->error('Fout bij het verzenden van notificatie mails.');
			}
		} 

		cancel();
	}
}

$transid = generate_transid();

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

if ($to_letscode)
{
	if ($to_name = $db->fetchColumn('select name from users where letscode = ?', array($to_letscode)))
	{
		$to_letscode .= ' ' . $to_name;
	}
}
if ($from_letscode)
{
	if ($from_name = $db->fetchColumn('select name from users where letscode = ?', array($from_letscode)))
	{
		$from_letscode .= ' ' . $from_name;
	}
}

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/mass_transaction.js"></script>
	<script src="' . $rootpath . 'js/combined_filter.js"></script>';

$h1 = 'Massa transactie';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-warning">';
echo '<div class="panel-heading">';
echo '<button class="btn btn-default" title="Toon invul-hulp" data-toggle="collapse" ';
echo 'data-target="#help">';
echo '<i class="fa fa-question"></i>';
echo ' Invul-hulp</button>';
echo '</div>';
echo '<div class="panel-body collapse" id="help">';

echo '<p>Met deze invul-hulp kan je snel alle bedragen van de massa-transactie invullen. ';
echo 'De bedragen kan je nadien nog individueel aanpassen alvorens de massa transactie uit te voeren. ';
echo '</p>';

echo '<form class="form form-horizontal" id="fill_in_aid">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-3 control-label">Vast bedrag</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" class="form-control" id="fixed" placeholder="vast bedrag" ';
echo 'min="0">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="percentage_balance" class="col-sm-3 control-label">';
echo 'Percentage op saldo (kan ook negatief zijn)</label>';
echo '<div class="col-sm-3">';
echo '<input type="number" class="form-control" id="percentage_balance"';
echo ' placeholder="percentage op saldo">';
echo '</div>';
echo '<div class="col-sm-3">';
echo '<input type="number" class="form-control" id="percentage_balance_days" ';
echo 'placeholder="aantal dagen" min="0">';
echo '</div>';
echo '<div class="col-sm-3">';
echo '<input type="number" class="form-control" id="percentage_balance_base" ';
echo 'placeholder="basis">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="respect_min_limit" class="col-sm-3 control-label">';
echo 'Respecteer minimum limieten</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="respect_min_limit" checked="checked">';
echo '</div>';
echo '</div>';

echo '<button class="btn btn-default" id="fill-in">Vul in</button>';

echo '</form>';

echo '</div>';
echo '</div>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="row">';
echo '<div class="col-xs-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs" id="nav-tabs">';

foreach ($st as $k => $s)
{
	$shsh = $s['hsh'] ?: '';
	$class_li = ($shsh == $hsh) ? ' class="active"' : '';
	$class_a  = ($s['cl']) ?: 'white';
	echo '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
	echo 'data-filter="' . $shsh . '">' . $s['lbl'] . '</a></li>';
}

echo '</ul>';

echo '<form method="post" class="form-horizontal">';

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '<input type="hidden" value="" name="selected_users" id="selected_users">';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<div class="form-group">';
echo '<label for="from_letscode" class="col-sm-2 control-label">';
echo "Van letscode (gebruik dit voor een 'één naar veel' transactie)";
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="from_letscode" name="from_letscode" ';
echo 'value="' . $from_letscode . '" ';
echo 'data-letsgroup-id="self">'; //data-thumbprint="' . time() . '">';
echo '</div>';
echo '</div>';

echo '</div>';

echo '<table class="table table-bordered table-striped table-hover panel-body footable"';
echo ' data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th data-filter="#filter">Naam</th>';
echo '<th data-sort-ignore="true">Bedrag</th>';
echo '<th data-hide="phone">Saldo</th>';
echo '<th data-hide="phone">Min.limit</th>';
echo '<th data-hide="phone">Max.limit</th>';
echo '<th data-hide="phone, tablet">Postcode</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $user_id => $user)
{
	$status_key = $status_ary[$user['status']];
	$status_key = ($status_key == 'active' && $newusertreshold < strtotime($user['adate'])) ? 'new' : $status_key;

	$hsh = ($st[$status_key]['hsh']) ?: '';
	$hsh .= ($status_key == 'leaving' || $status_key == 'new') ? $st['active']['hsh'] : '';
	$hsh .= ($status_key == 'active') ? $st['without-leaving-and-new']['hsh'] : '';

	$class = ($st[$status_key]['cl']) ? ' class="' . $st[$status_key]['cl'] . '"' : '';

	echo '<tr' . $class . ' data-user-id="' . $user_id . '">';

	echo '<td>';
	echo link_user($user, 'letscode');
	echo '</td>';

	echo '<td>';
	echo link_user($user, 'name');
	echo '</td>';

	echo '<td data-value="' . $hsh . '">';
	echo '<input type="number" name="amount[' . $user_id . ']" class="form-control" ';
	echo 'value="' . $amount[$user_id] . '" ';
	echo 'data-letscode="' . $user['letscode'] . '" ';
	echo 'data-user-id="' . $user_id . '" ';
	echo 'data-balance="' . $user['saldo'] . '" ';
	echo '>';
	echo '</td>';

	echo '<td>';
	$balance = $user['saldo'];
	if($balance < $user['minlimit'] || ($user['maxlimit'] != NULL && $balance > $user['maxlimit']))
	{
		echo '<span class="text-danger">' . $balance . '</span>';
	}
	else
	{
		echo $balance;
	}
	echo '</td>';

	echo '<td>' . $user['minlimit'] . '</td>';
	echo '<td>' . $user['maxlimit'] . '</td>';
	echo '<td>' . $user['postcode'] . '</td>';

	echo '</tr>';

}
echo '</tbody>';
echo '</table>';

echo '<div class="panel-heading">';

echo '<div class="form-group">';
echo '<label for="total" class="col-sm-2 control-label">Totaal ' . $currency . '</label>';
echo '<div class="col-sm-10">';
echo '<input type="number" class="form-control" id="total" readonly>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="to_letscode" class="col-sm-2 control-label">';
echo "Aan letscode (gebruik dit voor een 'veel naar één' transactie)";
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="to_letscode" name="to_letscode" ';
echo 'value="' . $to_letscode . '" ';
echo 'data-letsgroup-id="self" data-thumbprint="' . time() . '" ';
echo 'data-url="' . $rootpath . 'ajax/active_users.php?' . get_session_query_param() . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="description" name="description" ';
echo 'value="' . $description . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="mail_en" class="col-sm-2 control-label">';
echo 'Verstuur notificatie mails</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
echo ($mail_en) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="password" class="col-sm-2 control-label">Je paswoord (extra veiligheid)</label>';
echo '<div class="col-sm-10">';
echo '<input type="password" class="form-control" id="password" name="password" ';
echo 'value="" autocomplete="false" required>';
echo '</div>';
echo '</div>';

echo aphp('transactions', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
echo '<input type="submit" value="Massa transactie uitvoeren" name="zend" class="btn btn-success">';

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

echo '<input type="hidden" value="' . $transid . '" name="transid">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';

function mail_mass_transaction($mail_ary)
{
	global $db, $alert, $s_id, $base_url;

	if (!readconfigfromdb('mailenabled'))
	{
		$alert->warning('Mail functions are not enabled. ');
		return;
	}
	
	$from = readconfigfromdb('from_address');
	if (empty($from))
	{
		$alert->warning('Mail from_address is not set in configuration');
		return;
	}

	$from_many_bool = (is_array($mail_ary['from'])) ? true : false;

	$many_ary = ($from_many_bool) ? $mail_ary['from'] : $mail_ary['to'];
	$many_user_ids = array_keys($many_ary);

	$one_user_id = ($from_many_bool) ? $mail_ary['to'] : $mail_ary['from'];

	$one_user = $db->fetchAssoc('select u.id, u.name, u.letscode, c.value as mail
		from users u, contact c, type_contact tc
		where u.id = ?
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'', array($one_user_id));

	$mailaddr = array();

	$rs = $db->prepare('select u.id, c.value
		from users u, contact c, type_contact tc
		where u.status in (1, 2)
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$mailaddr[$row['id']] = $row['value'];
	}

	$r = "\r\n";
	$currency = readconfigfromdb('currency');
	$support = readconfigfromdb('support');
	$login_url = $base_url . '/login.php?login=*|LOGIN|*';
	$new_transaction_url = $base_url . '/transactions/add.php';

	$to = $merge_vars  = array();
	$to_log = '';
	$total = 0;
	$t = '*** Dit is een automatische mail. Niet beantwoorden a.u.b. ***';
	$t_one = link_user($one_user, null, false);

	$one_msg = $t . $r . $r;

	if (!$from_many_bool)
	{
		$one_msg .= 'Van ' . $t_one . $r;
		$one_msg .= 'Aan' . $r; 
	}
	else
	{
		$one_msg .= 'Van' . $r;
	}

	$users = $db->executeQuery('SELECT u.id,
			u.saldo, u.status, u.minlimit, u.maxlimit,
			u.name, u.letscode, u.login
		FROM users u
		WHERE u.status in (1, 2)
			AND u.id IN (?)',
		array($many_user_ids),
		array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

	foreach ($users as $user)
	{
		$amount = $many_ary[$user['id']]['amount'];
		$transid = $many_ary[$user['id']]['transid'];

		$one_msg .= link_user($user, null, false) . ': ';
		$one_msg .= $amount . ' ' . $currency;
		$one_msg .= ', transactie-id: ' . $transid . $r;

		$total += $amount;
		$to_log .= $user['letscode'] . ' ' . $user['name'] . ', ';

		$to[] = array(
			'email'	=> $mailaddr[$user['id']],
			'name'	=> $user['name'],
		);
		$merge_vars[] = array(
			'rcpt'	=> $mailaddr[$user['id']],
			'vars'	=> array(
				array(
					'name'		=> 'NAME',
					'content'	=> $user['name'],
				),
				array(
					'name'		=> 'BALANCE',
					'content'	=> $user['saldo'],
				),
				array(
					'name'		=> 'LETSCODE',
					'content'	=> $user['letscode'],
				),
				array(
					'name'		=> 'ID',
					'content'	=> $user['id'],
				),
				array(
					'name'		=> 'STATUS',
					'content'	=> ($user['status'] == 2) ? 'uitstapper' : 'actief',
				),
				array(
					'name'		=> 'MINLIMIT',
					'content'	=> $user['minlimit'],
				),
				array(
					'name'		=> 'MAXLIMIT',
					'content'	=> $user['maxlimit'],
				),
				array(
					'name'		=> 'LOGIN',
					'content'	=> $user['login'],
				),
				array(
					'name'		=> 'AMOUNT',
					'content'	=> $many_ary[$user['id']]['amount'],
				),
				array(
					'name'		=> 'TRANSID',
					'content'	=> $many_ary[$user['id']]['transid'],
				),
			),
		);
	}

	if ($from_many_bool)
	{
		$one_msg .= $r . 'Aan ' . $t_one . $r . $r;
	}

	$one_msg .= 'Totaal: ' . $total . ' ' . $currency . $r . $r;
	$one_msg .= 'Voor: ' . $mail_ary['description'];

	$text = $t . $r . $r;
	$html = $t . '<br>';

	$text .= 'Notificatie transactie' . $r . $r;
	$html .= '<h2>Notificatie transactie</h2>';

	$t_many = '*|LETSCODE|* *|NAME|*';

	$t = ($from_many_bool) ? $t_many : $t_one;

	$text .= 'Van ' . $t. $r;
	$html .= '<p>Van ' . $t . '</p>';

	$t = ($from_many_bool) ? $t_one : $t_many;

	$text .= 'Aan ' . $t . $r;
	$html .= '<p>Aan ' . $t . '</p>';

	$t = 'Bedrag: *|AMOUNT|* ' . $currency;

	$text .=  $t . $r;
	$html .= '<p>' . $t . '</p>';

	$t = 'Voor: ' . $mail_ary['description'];

	$text .=  $t . $r . $r;
	$html .= '<p>' . $t . '</p><br>';

	$t = 'Transactie id: *|TRANSID|*';

	$text .=  $t . $r;
	$html .= '<p>' . $t . '</p>';

	$text .= 'Je huidig saldo bedraagt nu *|BALANCE|* ' . $currency . $r;
	$html .= '<p>Je huidig saldo bedraagt nu <b>*|BALANCE|* </b> ' . $currency . '</p>';

	$text .= 'Minimum limiet: *|MINLIMIT|* ' . $currency . ', Maximum limiet: *|MAXLIMIT|* ' . $currency . $r;
	$html .= '<p>Minimum limiet: <b>*|MINLIMIT|*</b> ' . $currency . ', Maximum limiet: <b>*|MAXLIMIT|*</b> ' . $currency . '</p>';

	$text .= 'Status: *|STATUS|*' . $r;
	$html .= '<p>Status: <b>*|STATUS|*</b></p>';

	$text .= 'Login:  ' . $login_url . $r . $r;
	$html .= '<p>Login: ' . $login_url . '</p>';

	$text .= 'Nieuwe transactie ingeven: ' . $new_transaction_url . $r . $r;
	$html .= '<p>Klik <a href="' . $new_transaction_url . '">hier</a> om een nieuwe transactie in te geven.</p>';

	$subject = '['. readconfigfromdb('systemtag') .'] - Nieuwe transactie.';

	$message = array(
		'subject'		=> $subject,
		'text'			=> $text,
		'html'			=> $html,
		'from_email'	=> $from,
		'to'			=> $to,
		'merge_vars'	=> $merge_vars,
	);

	try
	{
		$mandrill = new Mandrill();
		$mandrill->messages->send($message, true);
	}
	catch (Mandrill_Error $e)
	{
		// Mandrill errors are thrown as exceptions
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		return false;
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	$subject = '['. readconfigfromdb('systemtag') .'] - Nieuwe massa transactie.';

	log_event($s_id, 'Mail', 'Massa transaction mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to_log);

	$to = array(array(
		'name' 	=> $one_user['name'],
		'email'	=> $one_user['mail'],
	));

	if ($one_user_id != $s_id)
	{
		$s_user = $db->fetchAssoc('select u.name, c.value as mail
			from users u, contact c, type_contact tc
			where u.id = ?
				and u.id = c.id_user
				and c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'', array($s_id));
		$to[] = array(
			'name'	=> $s_user['name'],
			'email' => $s_user['mail'],
		);
	}

	$message = array(
		'subject'		=> $subject,
		'text'			=> $one_msg,
		'from_email'	=> $from,
		'to'			=> $to,
		'merge_vars'	=> $merge_vars,
	);

	$mandrill->messages->send($message, true);

	return true;
}

function cancel()
{
	header('Location: ' . generate_url('mass_transaction.php'));
	exit;
}
