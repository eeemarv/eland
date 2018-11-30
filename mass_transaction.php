<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';
require_once __DIR__ . '/include/transactions.php';

$tschema = $app['this_group']->get_schema();

$q = $_POST['q'] ?? ($_GET['q'] ?? '');
$hsh = $_POST['hsh'] ?? ($_GET['hsh'] ?? '096024');
$selected_users = $_POST['selected_users'] ?? '';
$selected_users = ltrim($selected_users, '.');
$selected_users = explode('.', $selected_users);
$selected_users = array_combine($selected_users, $selected_users);

$submit = isset($_POST['zend']) ? true : false;

$st = [
	'active'	=> [
		'lbl'	=> 'Actief',
		'st'	=> 1,
		'hsh'	=> '58d267',
	],
	'without-new-and-leaving' => [
		'lbl'	=> 'Actief zonder uit- en instappers',
		'st'	=> '123',
		'hsh'	=> '096024',
	],
	'new'		=> [
		'lbl'	=> 'Instappers',
		'st'	=> 3,
		'hsh'	=> 'e25b92',
		'cl'	=> 'success',
	],
	'leaving'	=> [
		'lbl'	=> 'Uitstappers',
		'st'	=> 2,
		'hsh'	=> 'ea4d04',
		'cl'	=> 'danger',
	],
	'inactive'	=> [
		'lbl'	=> 'Inactief',
		'st'	=> 0,
		'hsh'	=> '79a240',
		'cl'	=> 'inactive',
	],
	'info-packet'	=> [
		'lbl'	=> 'Info-pakket',
		'st'	=> 5,
		'hsh'	=> '2ed157',
		'cl'	=> 'warning',
	],
	'info-moment'	=> [
		'lbl'	=> 'Info-moment',
		'st'	=> 6,
		'hsh'	=> '065878',
		'cl'	=> 'info',
	],
	'all'		=> [
		'lbl'	=> 'Alle',
	],
];

$status_ary = [
	0 	=> 'inactive',
	1 	=> 'active',
	2 	=> 'leaving',
	3	=> 'new',
	5	=> 'info-packet',
	6	=> 'info-moment',
	7	=> 'extern',
	123 => 'without-new-and-leaving',
];

$users = [];

$rs = $app['db']->prepare(
	'select id, name, letscode,
		accountrole, status, saldo,
		minlimit, maxlimit, adate,
		postcode
	from ' . $tschema . '.users
	where status IN (0, 1, 2, 5, 6)
	order by letscode');

$rs->execute();

while ($row = $rs->fetch())
{

// hack eLAS compatibility (in eLAND limits can be null)

	$row['minlimit'] = $row['minlimit'] === -999999999 ? '' : $row['minlimit'];
	$row['maxlimit'] = $row['maxlimit'] === 999999999 ? '' : $row['maxlimit'];

	$users[$row['id']] = $row;
}

[$to_letscode] = isset($_POST['to_letscode']) ? explode(' ', trim($_POST['to_letscode'])) : [''];
[$from_letscode] = isset($_POST['from_letscode']) ? explode(' ', trim($_POST['from_letscode'])) : [''];

$amount = $_POST['amount'] ?? [];
$description = $_POST['description'] ?? '';
$description = trim($description);

$transid = $_POST['transid'] ?? '';

$mail_en = isset($_POST['mail_en']) ? true : false;

if ($submit)
{
	$verify = isset($_POST['verify']) ? true : false;

	if (!$verify)
	{
		$errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
	}

	if (!$description)
	{
		$errors[] = 'Vul een omschrijving in.';
	}

	if ($to_letscode && $from_letscode)
	{
		$errors[] = '\'Van Account Code\' en \'Aan Account Code\' kunnen niet beide ingevuld worden.';
	}
	else if (!($to_letscode || $from_letscode))
	{
		$errors[] = '\'Van Account Code\' OF \'Aan Account Code\' moet ingevuld worden.';
	}
	else
	{
		$to_one = $to_letscode ? true : false;
		$letscode = $to_one ? $to_letscode : $from_letscode;

		$one_uid = $app['db']->fetchColumn('select id
			from ' . $tschema . '.users
			where letscode = ?', [$letscode]);

		if (!$one_uid)
		{
			$err = 'Geen bestaande Account Code in veld \'';
			$err .= $to_one ? 'Aan': 'Van';
			$err .= ' Account Code\'.';
			$errors[] = $err;
		}
		else
		{
			unset($amount[$one_uid]);
		}
	}

	$filter_options = [
		'options'	=> [
			'min_range' => 0,
		],
	];

	$count = 0;

	foreach ($amount as $uid => $amo)
	{
		if (!isset($selected_users[$uid]))
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

	if ($app['db']->fetchColumn('select id
		from ' . $tschema . '.transactions
		where transid = ?', [$transid]))
	{
		$errors[] = 'Een dubbele boeking van een transactie werd voorkomen.';
	}

	if ($error_token = $app['form_token']->get_error())
	{
		$errors[] = $error_token;
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
	}
	else
	{
		$transactions = $transid_ary = [];

		$app['db']->beginTransaction();

		$cdate = gmdate('Y-m-d H:i:s');

		$one_field = $to_one ? 'to' : 'from';
		$many_field = $to_one ? 'from' : 'to';

		$mail_ary = [
			$one_field 		=> $one_uid,
			'description'	=> $description,
			'date'			=> $cdate,
		];

		$alert_success = $log_many = '';
		$total = 0;

		try
		{

			foreach ($amount as $many_uid => $amo)
			{
				if (!isset($selected_users[$many_uid]))
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
				$alert_success .= '  met bedrag ' . $amo .' ';
				$alert_success .= $app['config']->get('currency', $tschema);
				$alert_success .= ' uitgevoerd.<br>';

				$log_many .= $many_user['letscode'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

				$trans = [
					'id_to' 		=> $to_id,
					'id_from' 		=> $from_id,
					'amount' 		=> $amo,
					'description' 	=> $description,
					'date' 			=> $cdate,
					'cdate' 		=> $cdate,
					'transid'		=> $transid,
					'creator'		=> $s_master ? 0 : $s_id,
				];

				$app['db']->insert($tschema . '.transactions', $trans);

				$mail_ary[$many_field][$many_uid] = [
					'amount'	=> $amo,
					'transid' 	=> $transid,
				];

				$transid_ary[] = $transid;

				$app['db']->executeUpdate('update ' . $tschema . '.users
					set saldo = saldo ' . (($to_one) ? '- ' : '+ ') . '?
					where id = ?', [$amo, $many_uid]);

				$total += $amo;

				$transid = generate_transid();

				$transactions[] = $trans;
			}

			$app['db']->executeUpdate('update ' . $tschema . '.users
				set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . '?
				where id = ?', [$total, $one_uid]);

			$app['db']->commit();
		}
		catch (Exception $e)
		{
			$app['alert']->error('Fout bij het opslaan.');
			$app['db']->rollback();
			throw $e;
		}

		$app['autominlimit']->init();

		foreach($transactions as $t)
		{
			$app['autominlimit']->process($t['id_from'], $t['id_to'], $t['amount']);
		}

		if ($to_one)
		{
			foreach ($transactions as $t)
			{
				$app['predis']->del($tschema . '_user_' . $t['id_from']);
			}

			$app['predis']->del($tschema . '_user_' . $t['id_to']);
		}
		else
		{
			foreach ($transactions as $t)
			{
				$app['predis']->del($tschema . '_user_' . $t['id_to']);
			}

			$app['predis']->del($tschema . '_user_' . $t['id_from']);
		}

		$alert_success .= 'Totaal: ' . $total . ' ';
		$alert_success .= $app['config']->get('currency', $tschema);
		$app['alert']->success($alert_success);

		$log_one = $users[$one_uid]['letscode'] . ' ';
		$log_one .= $users[$one_uid]['name'];
		$log_one .= '(Total amount: ' . $total . ' ';
		$log_one .= $app['config']->get('currency', $tschema);
		$log_one .= ')';

		$log_many = rtrim($log_many, ', ');
		$log_str = 'Mass transaction from ';
		$log_str .= $to_one ? $log_many : $log_one;
		$log_str .= ' to ';
		$log_str .= $to_one ? $log_one : $log_many;

		$app['monolog']->info('trans: ' . $log_str, ['schema' => $tschema]);

		if ($s_master)
		{
			$app['alert']->warning('Master account: geen mails verzonden.');
		}
		else if ($mail_en)
		{
			$mail_ary['transid_ary'] = $transid_ary;

			if (mail_mass_transaction($mail_ary))
			{
				$app['alert']->success('Notificatie mails verzonden.');
			}
			else
			{
				$app['alert']->error('Fout bij het verzenden van notificatie mails.');
			}
		}

		cancel();
	}
}
else
{
	$mail_en = true;
}

$transid = generate_transid();

if ($to_letscode)
{
	if ($to_name = $app['db']->fetchColumn('select name
		from ' . $tschema . '.users
		where letscode = ?', [$to_letscode]))
	{
		$to_letscode .= ' ' . $to_name;
	}
}
if ($from_letscode)
{
	if ($from_name = $app['db']->fetchColumn('select name
		from ' . $tschema . '.users
		where letscode = ?', [$from_letscode]))
	{
		$from_letscode .= ' ' . $from_name;
	}
}

$group_minlimit = $app['config']->get('minlimit', $tschema);
$group_maxlimit = $app['config']->get('maxlimit', $tschema);

$app['assets']->add(['typeahead', 'typeahead.js', 'mass_transaction.js', 'combined_filter.js']);

$h1 = 'Massa transactie';
$fa = 'exchange';

include __DIR__ . '/include/header.php';

echo '<div class="panel panel-warning">';
echo '<div class="panel-heading">';
echo '<button class="btn btn-default" title="Toon invul-hulp" data-toggle="collapse" ';
echo 'data-target="#help" type="button">';
echo '<i class="fa fa-question"></i>';
echo ' Invul-hulp</button>';
echo '</div>';
echo '<div class="panel-body collapse" id="help">';

echo '<p>Met deze invul-hulp kan je snel alle bedragen van de massa-transactie invullen. ';
echo 'De bedragen kan je nadien nog individueel aanpassen alvorens de massa transactie uit te voeren. ';
echo '</p>';

echo '<form class="form form-horizontal" id="fill_in_aid">';

echo '<div class="pan-sub bg-warning">';

echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-2 control-label">Vast bedrag</label>';
echo '<div class="col-sm-10">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $tschema);
echo '</span>';
echo '<input type="number" class="form-control margin-bottom" id="fixed" ';
echo 'min="0">';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

/**/
echo '<div class="pan-sub bg-warning">';

echo '<h4>Variabel deel</h4>';

//
echo '<div class="form-group">';
echo '<label for="fixed" class="col-sm-2 control-label">Over periode</label>';
echo '<div class="col-sm-10">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon">dagen</span>';
echo '<input type="number" class="form-control margin-bottom" id="var_days" ';
echo 'min="0">';
echo '</div>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_balance" class="col-sm-2 control-label">';
echo 'Promille op saldo</label>';
echo '<div class="col-sm-5">';

echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';
echo '<input type="number" class="form-control margin-bottom" id="var_balance">';
echo '</div>';
echo '<p>Berekend op gewogen gemiddelde van saldo. Kan ook negatief zijn!</p>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $tschema);
echo ': basis';
echo '</span>';
echo '<input type="number" class="form-control" id="var_base">';
echo '</div>';
echo '<p>De basis waartegenover berekend wordt. Kan ook afwijkend van nul zijn.</p>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_trans_in" class="col-sm-2 control-label">';
echo 'Promille op transacties in</label>';
echo '<div class="col-sm-5">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';
echo '<input type="number" class="form-control" id="var_trans_in">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo 'excl. Account Codes';
echo '</span>';
echo '<input type="text" class="form-control" id="var_ex_code_in">';
echo '</div>';
echo '<p>Exclusief tegenpartijen: ';
echo 'Account Codes gescheiden door komma\'s</p>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_trans_out" class="col-sm-2 control-label">';
echo 'Promille op transacties uit</label>';
echo '<div class="col-sm-5">';

echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';

echo '<input type="number" class="form-control" id="var_trans_out">';

echo '</div>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo 'excl. Account Codes';
echo '</span>';

echo '<input type="text" class="form-control" id="var_ex_code_out">';
echo '</div>';
echo '<p>Exclusief tegenpartijen: ';
echo 'Account Codes gescheiden door komma\'s</p>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_minimum" class="col-sm-2 control-label">';
echo 'Minimum - maximum</label>';
echo '<div class="col-sm-5">';

echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $tschema);
echo ': min';
echo '</span>';

echo '<input type="number" class="form-control margin-bottom" id="var_min">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-5">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $tschema);
echo ': max';
echo '</span>';

echo '<input type="number" class="form-control" id="var_max">';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';
/**/

echo '<div class="form-group">';
echo '<label for="respect_minlimit" class="col-sm-3 control-label">';
echo 'Respecteer minimum limieten</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="respect_minlimit" checked="checked">';
echo '</div>';
echo '</div>';

if ($app['config']->get('minlimit', $tschema) !== ''
	|| $app['config']->get('maxlimit', $tschema) !== '')
{
	echo '<ul>';

	if ($app['config']->get('minlimit', $tschema) !== '')
	{
		echo '<li>Minimum Systeemslimiet: ';
		echo $app['config']->get('minlimit', $tschema);
		echo ' ';
		echo $app['config']->get('currency', $tschema);
		echo '</li>';
	}

	if ($app['config']->get('maxlimit', $tschema) !== '')
	{
		echo '<li>Maximum Systeemslimiet: ';
		echo $app['config']->get('maxlimit', $tschema);
		echo ' ';
		echo $app['config']->get('currency', $tschema);
		echo '</li>';
	}

	echo '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
	echo 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

	echo '</ul>';
}

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
	$shsh = $s['hsh'] ?? '';
	$class_li = ($shsh == $hsh) ? ' class="active"' : '';
	$class_a  = $s['cl'] ?? 'white';

	echo '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
	echo 'data-filter="' . $shsh . '">' . $s['lbl'] . '</a></li>';
}

echo '</ul>';

echo '<form method="post" class="form-horizontal" autocomplete="off">';

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '<input type="hidden" value="" name="selected_users" id="selected_users">';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<div class="form-group">';
echo '<label for="from_letscode" class="col-sm-2 control-label">';
echo 'Van Account Code';
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="from_letscode" name="from_letscode" ';
echo 'value="';
echo $from_letscode;
echo '" ';
echo 'data-newuserdays="';
echo $app['config']->get('newuserdays', $tschema);
echo '" ';
echo 'data-typeahead="';
echo $app['typeahead']->get(['users_active', 'users_inactive', 'users_ip', 'users_im']);
echo '">';
echo '<p>Gebruik dit voor een "Eén naar veel" transactie.';
echo 'Alle ingevulde bedragen hieronder worden van dit Account gehaald.</p>';
echo '</div>';
echo '</div>';

echo '</div>';

echo '<table class="table table-bordered table-striped table-hover panel-body footable" ';
echo 'data-filter="#combined-filter" data-filter-minimum="1" ';
echo 'data-minlimit="' . $group_minlimit . '" ';
echo 'data-maxlimit="' . $group_maxlimit . '"';
echo '>';
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
	$hsh .= ($status_key == 'active') ? $st['without-new-and-leaving']['hsh'] : '';

	$class = isset($st[$status_key]['cl']) ? ' class="' . $st[$status_key]['cl'] . '"' : '';

	echo '<tr' . $class . ' data-user-id="' . $user_id . '">';

	echo '<td>';
	echo link_user($user, $tschema, true, false, 'letscode');
	echo '</td>';

	echo '<td>';
	echo link_user($user, $tschema, true, false, 'name');
	echo '</td>';

	echo '<td data-value="' . $hsh . '">';
	echo '<input type="number" name="amount[' . $user_id . ']" class="form-control" ';
	echo 'value="';
	echo $amount[$user_id] ?? '';
	echo '" ';
	echo 'min="0" ';
	echo 'data-letscode="' . $user['letscode'] . '" ';
	echo 'data-user-id="' . $user_id . '" ';
	echo 'data-balance="' . $user['saldo'] . '" ';
	echo 'data-minlimit="' . $user['minlimit'] . '"';
	echo '>';
	echo '</td>';

	echo '<td>';

	$balance = $user['saldo'];

	$minlimit = $user['minlimit'] === '' ? $group_minlimit : $user['minlimit'];
	$maxlimit = $user['maxlimit'] === '' ? $group_maxlimit : $user['maxlimit'];

	if (($minlimit !== '' && $balance < $minlimit)
		|| ($maxlimit !== '' && $balance > $maxlimit))
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
echo '<label for="total" class="col-sm-2 control-label">Totaal ';
echo $app['config']->get('currency', $tschema);
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="number" class="form-control" id="total" readonly>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="to_letscode" class="col-sm-2 control-label">';
echo 'Aan Account Code';
echo '</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="to_letscode" name="to_letscode" ';
echo 'value="';
echo $to_letscode;
echo '" ';
echo 'data-typeahead-source="from_letscode">';
echo '<p>Gebruik dit voor een "Veel naar één" transactie. Bijvoorbeeld, een ledenbijdrage. ';
echo 'Alle ingevulde bedragen hierboven gaan naar dit Account.</p>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="description" ';
echo 'name="description" ';
echo 'value="' . $description . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="mail_en" class="col-sm-2 control-label">';
echo 'Verstuur notificatie mails</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
echo $mail_en ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<div class="col-sm-12">';
echo '<input type="checkbox" name="verify"';
echo ' value="1" required> ';
echo 'Ik heb nagekeken dat de juiste bedragen en de juiste "Van" of "Aan" ';
echo 'Account Code ingevuld zijn.';
echo '</div>';
echo '</div>';

echo aphp('transactions', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
echo '<input type="submit" value="Massa transactie uitvoeren" name="zend" class="btn btn-success">';
echo $app['form_token']->get_hidden_input();

echo '</div>';
echo '</div>';

echo '</div>';

echo '<input type="hidden" value="' . $transid . '" name="transid">';

echo '</form>';

include __DIR__ . '/include/footer.php';

/**
 *
 */
function mail_mass_transaction($mail_ary)
{
	global $app, $s_id;

	$tschema = $app['this_group']->get_schema();

	if (!$app['config']->get('mailenabled', $tschema))
	{
		$app['alert']->warning('Mail functions are not enabled. ');
		return;
	}

	$trans_map = [];

	$trans = $app['db']->executeQuery('select id, transid
		from ' . $tschema . '.transactions
		where transid in (?)',
		[$mail_ary['transid_ary']],
		[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

	foreach ($trans as $t)
	{
		$trans_map[$t['transid']] = $t['id'];
	}

	$from_many_bool = is_array($mail_ary['from']) ? true : false;

	$many_ary = ($from_many_bool) ? $mail_ary['from'] : $mail_ary['to'];

	$many_user_ids = array_keys($many_ary);

	$one_user_id = ($from_many_bool) ? $mail_ary['to'] : $mail_ary['from'];

	$common_vars = [
		'group'		=> [
			'name'			=> $app['config']->get('systemname', $tschema),
			'tag'			=> $app['config']->get('systemtag', $tschema),
			'support'		=> explode(',', $app['config']->get('support', $tschema)),
			'currency'		=> $app['config']->get('currency', $tschema),
		],
		'description'			=> $mail_ary['description'],
		'new_transaction_url'	=> $app['base_url'] . '/transactions.php?add=1',
		'from_many'				=> $from_many_bool,
	];

	$from_user_id = $to_user_id = $one_user_id;

	$users = $app['db']->executeQuery('select u.id,
			u.saldo, u.status, u.minlimit, u.maxlimit,
			u.name, u.letscode
		from ' . $tschema . '.users u
		where u.status in (1, 2)
			and u.id in (?)',
		[$many_user_ids],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($users as $user)
	{
		$user_id = $user['id'];

		if ($from_many_bool)
		{
			$from_user_id = $user_id;
		}
		else
		{
			$to_user_id = $user_id;
		}

		$vars = array_merge($common_vars, [
			'amount' 			=> $many_ary[$user_id]['amount'],
			'transid' 			=> $many_ary[$user_id]['transid'],
			'from_user' 		=> link_user($from_user_id, $tschema, false),
			'to_user'			=> link_user($to_user_id, $tschema, false),
			'transaction_url'	=> $app['base_url'] . '/transactions.php?id=' . $trans_map[$many_ary[$user_id]['transid']],
			'user'				=> $user,
			'interlets'			=> false,
			'url_login'			=> $app['base_url'] . '/login.php?login=' . $user['letscode'],
		]);

		$app['queue.mail']->queue([
			'to'		=> $user_id,
			'template'	=> 'transaction',
			'vars'		=> $vars,
		]);
	}

	$total = 0;

	$users = [];

	$user_ids = $app['db']->executeQuery('select u.id
		from ' . $tschema . '.users u
		where u.id in (?)',
		[$many_user_ids],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($user_ids as $u)
	{
		$user_id = $u['id'];

		$users[] = [
			'url'		=> $app['base_url'] . '/users.php?id=' . $user_id,
			'text'		=> link_user($user_id, $tschema, false),
			'amount'	=> $many_ary[$user_id]['amount'],
			'id'		=> $user_id,
		];

		$total += $many_ary[$user_id]['amount'];

		$text .= link_user($user_id, $tschema, false) . $t . $t . $many_ary[$user_id]['amount'];
		$text .= ' ' . $currency . $r;
	}

	$vars = array_merge($common_vars, [
		'users'		=> $users,
		'user'		=> [
			'url'	=> $app['base_url'] . '/users.php?id=' . $one_user_id,
			'text'	=> link_user($one_user_id, $tschema, false),
		],
		'total'		=> $total,
	]);

	$app['queue.mail']->queue([
		'to' 		=> ['admin', $s_id, $one_user_id],
		'subject' 	=> $subject,
		'text' 		=> $text,
		'template'	=> 'admin_mass_transaction',
		'vars'		=> $vars,
	]);

	return true;
}

function cancel()
{
	header('Location: ' . generate_url('mass_transaction'));
	exit;
}
