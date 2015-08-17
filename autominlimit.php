<?php
ob_start();

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$q = ($_POST['q']) ?: (($_GET['q']) ?: '');
$hsh = ($_POST['hsh']) ?: (($_GET['hsh']) ?: '');
$selected_users = $_POST['selected_users'];
$selected_users = ltrim($selected_users, '.');
$selected_users = explode('.', $selected_users);
$selected_users = array_combine($selected_users, $selected_users);

$st = array(
	'all'		=> array(
		'lbl'	=> 'Alle',
	),
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

$users = $db->GetAssoc(
	'SELECT id, fullname, letscode,
		accountrole, status, saldo,
		minlimit, maxlimit, adate,
		postcode
	FROM users
	WHERE status IN (0, 1, 2, 5, 6)
	ORDER BY letscode');

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

		if ($password != $db->GetOne('select password from users where id = ' . $s_id))
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

		$one_uid = $db->GetOne('select id from users where letscode = \'' . $letscode . '\'');

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

	if ($db->GetOne('select id from transactions where transid = \'' . $transid . '\''))
	{
		$errors[] = 'Een dubbele boeking van een transactie werd voorkomen.';
	}

	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
	else
	{
		$db->StartTrans();

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

			$alert_success .= 'Transactie van gebruiker ' . $from_user['letscode'] . ' ' . $from_user['fullname'];
			$alert_success .= ' naar ' . $to_user['letscode'] . ' ' . $to_user['fullname'];
			$alert_success .= '  met bedrag ' . $amo .' ' . $currency . ' uitgevoerd.<br>';

			$log_many .= $many_user['letscode'] . ' ' . $many_user['fullname'] . '(' . $amo . '), ';

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

			$db->AutoExecute('transactions', $trans, 'INSERT');

			$db->Execute('update users
				set saldo = saldo ' . (($to_one) ? '- ' : '+ ')  . $amo . '
				where id = ' . $many_uid);

			$total += $amo;

			$transid = generate_transid();
		}

		$db->Execute('update users
			set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . $total . '
			where id = ' . $one_uid);

		if ($db->CompleteTrans())
		{
			$alert_success .= 'Totaal: ' . $total . ' ' . $currency;
			$alert->success($alert_success);

			$log_one = $users[$one_uid]['letscode'] . ' ' . $users[$one_uid]['fullname'] . ' (Total amount: ' . $total . ' ' . $currency . ')'; 
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

			$users = $db->GetAssoc(
				'SELECT id, fullname, letscode,
					accountrole, status, saldo, minlimit, maxlimit, adate
				FROM users
				WHERE status IN (0, 1, 2, 5, 6)
				ORDER BY letscode');

		}
		else
		{
			$alert->error('Fout bij het opslaan.');
		} 
	}
}

$transid = '';

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$letsgroup_id = $db->GetOne('select id from letsgroups where apimethod = \'internal\'');

$h1 = 'Automatische minimum limiet';
$fa = 'arrows-v';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form class="form-horizontal" method="post">';

echo '<div class="form-group">';
echo '<label for="enabled" class="col-sm-3 control-label">';
echo 'Zet de automatische minimum limiet aan</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="enabled" name="enabled" ';
echo ($a['enabled']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Voor accounts</h3>';
echo '<p>Enkel actieve accounts kunnen een automatische minimum limiet hebben.</p>';

echo '<div class="form-group">';
echo '<label for="all_without_new_and_leaving" class="col-sm-3 control-label">';
echo 'Alle actieve zonder in- en uitstappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="all_without_new_and_leaving" name="all_without_new_and_leaving" ';
echo ($a['all_without_new_and_leaving']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="new" class="col-sm-3 control-label">';
echo 'Instappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="new" name="new" ';
echo ($a['new']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="leaving" class="col-sm-3 control-label">';
echo 'Uitstappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="leaving" name="leaving" ';
echo ($a['leaving']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="inclusive" class="col-sm-3 control-label">';
echo 'Inclusief (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="inclusive" name="inclusive" ';
echo ($a['inclusive']) ? ' checked="checked"' : '';
echo ' class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="exclusive" class="col-sm-3 control-label">';
echo 'Exclusief (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="exclusive" name="exclusive" ';
echo ($a['exclusive']) ? ' checked="checked"' : '';
echo ' class="form-control">';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Begrenzingen</h3>';
echo '<p>Grenzen van de automatische minimum limiet (in ' . $currency . ').</p>';

echo '<div class="form-group">';
echo '<label for="max" class="col-sm-3 control-label">';
echo 'Bovengrens</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="max" name="max" ';
echo 'value="' . $a['max'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="max" class="col-sm-3 control-label">';
echo 'Ondergrens</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="min" name="min" ';
echo 'value="' . $a['min'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Triggers voor verruiming.</h3>';
echo '<h4>1. Ontvangen transacties</h4>';

echo '<div class="form-group">';
echo '<label for="trans_percentage" class="col-sm-3 control-label">';
echo 'Percentage van ontvangen bedrag</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="trans_percentage" name="trans_percentage" ';
echo 'value="' . $a['trans_percentage'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="trans_exclusive" class="col-sm-3 control-label">';
echo 'Exclusief tegenpartijen (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
echo 'value="' . $a['trans_exclusive'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="trans_base" class="col-sm-3 control-label">';
echo 'Enkel wanneer account is boven bedrag (' . $currency . ')</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="trans_base" name="trans_base" ';
echo 'value="' . $a['trans_base'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<h4>2. Anciënniteit (éénmalig)</h4>';

echo '<div class="form-group">';
echo '<label for="days" class="col-sm-3 control-label">';
echo 'Nieuwe minimum limiet in ' . $currency;
echo ' (deze wordt enkel ingesteld wanneer lager dan de huidige)</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="days" name="days" ';
echo 'value="' . $a['days'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="days" class="col-sm-3 control-label">';
echo 'Dagen</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="days" name="days" ';
echo 'value="' . $a['days'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="min_transactions" class="col-sm-3 control-label">';
echo 'Minimum aantal ontvangen transacties</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="min_transactions" name="min_transactions" ';
echo 'value="' . $a['min_transactions'] . '" ';
echo 'class="form-control" min="0">';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Trigger voor inkrimping.</h3>';

echo '<h4>Inactiveit</h4>';

echo '<div class="form-group">';
echo '<label for="min_transactions" class="col-sm-3 control-label">';
echo 'Minimum aantal ontvangen transacties</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="min_transactions" name="min_transactions" ';
echo 'value="' . $a['min_transactions'] . '" ';
echo 'class="form-control" min="0">';
echo '</div>';
echo '</div>';


echo '</form>';

echo '</div>';
echo '</div>';


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
echo 'data-letsgroup-id="' . $letsgroup_id . '">';
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
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$user_id .'">';
	echo $user['letscode'];
	echo '</a></td>';
	
	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$user_id .'">';
	echo htmlspecialchars($user['fullname'],ENT_QUOTES).'</a></td>';
	
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
echo 'data-letsgroup-id="' . $letsgroup_id . '">';
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
echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="password" class="form-control" id="password" name="password" ';
echo 'value="" autocomplete="false" required>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'transactions/alltrans.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Massa transactie uitvoeren" name="zend" class="btn btn-success">';

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

echo '<input type="hidden" value="' . $transid . '" name="transid">';

echo '</form>';

include $rootpath . 'includes/inc_footer.php';
