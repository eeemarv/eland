<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$q = $_POST['q'] ?? ($_GET['q'] ?? '');
$hsh = $_POST['hsh'] ?? ($_GET['hsh'] ?? '096024');
$selected_users = $_POST['selected_users'] ?? '';
$selected_users = ltrim($selected_users, '.');
$selected_users = explode('.', $selected_users);
$selected_users = array_combine($selected_users, $selected_users);

$submit = isset($_POST['zend']);

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
	from ' . $app['tschema'] . '.users
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
			from ' . $app['tschema'] . '.users
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
		from ' . $app['tschema'] . '.transactions
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
		$transactions = [];

		$app['db']->beginTransaction();

		$cdate = gmdate('Y-m-d H:i:s');

		$alert_success = $log_many = '';
		$total_amount = 0;

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
				$to_id = $to_one ? $one_uid : $many_uid;
				$from_id = $to_one ? $many_uid : $one_uid;
				$from_user = $users[$from_id];
				$to_user = $users[$to_id];

				$alert_success .= 'Transactie van gebruiker ' . $from_user['letscode'] . ' ' . $from_user['name'];
				$alert_success .= ' naar ' . $to_user['letscode'] . ' ' . $to_user['name'];
				$alert_success .= '  met bedrag ' . $amo .' ';
				$alert_success .= $app['config']->get('currency', $app['tschema']);
				$alert_success .= ' uitgevoerd.<br>';

				$log_many .= $many_user['letscode'] . ' ' . $many_user['name'] . '(' . $amo . '), ';

				$transaction = [
					'id_to' 		=> $to_id,
					'id_from' 		=> $from_id,
					'amount' 		=> $amo,
					'description' 	=> $description,
					'date' 			=> $cdate,
					'cdate' 		=> $cdate,
					'transid'		=> $transid,
					'creator'		=> $app['s_master'] ? 0 : $app['s_id'],
				];

				$app['db']->insert($app['tschema'] . '.transactions', $transaction);
				$transaction['id'] = $app['db']->lastInsertId($app['tschema'] . '.transactions_id_seq');

				$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
					set saldo = saldo ' . (($to_one) ? '- ' : '+ ') . '?
					where id = ?', [$amo, $many_uid]);

				$total_amount += $amo;

				$transid = $app['transaction']->generate_transid($app['s_id'], $app['server_name']);

				$transactions[] = $transaction;
			}

			$app['db']->executeUpdate('update ' . $app['tschema'] . '.users
				set saldo = saldo ' . (($to_one) ? '+ ' : '- ') . '?
				where id = ?', [$total_amount, $one_uid]);

			$app['db']->commit();
		}
		catch (Exception $e)
		{
			$app['alert']->error('Fout bij het opslaan.');
			$app['db']->rollback();
			throw $e;
		}

		$app['autominlimit']->init($app['tschema']);

		foreach($transactions as $t)
		{
			$app['autominlimit']->process($t['id_from'], $t['id_to'], $t['amount']);
		}

		if ($to_one)
		{
			foreach ($transactions as $t)
			{
				$app['predis']->del($app['tschema'] . '_user_' . $t['id_from']);
			}

			$app['predis']->del($app['tschema'] . '_user_' . $t['id_to']);
		}
		else
		{
			foreach ($transactions as $t)
			{
				$app['predis']->del($app['tschema'] . '_user_' . $t['id_to']);
			}

			$app['predis']->del($app['tschema'] . '_user_' . $t['id_from']);
		}

		$alert_success .= 'Totaal: ' . $total_amount . ' ';
		$alert_success .= $app['config']->get('currency', $app['tschema']);
		$app['alert']->success($alert_success);

		$log_one = $users[$one_uid]['letscode'] . ' ';
		$log_one .= $users[$one_uid]['name'];
		$log_one .= '(Total amount: ' . $total_amount . ' ';
		$log_one .= $app['config']->get('currency', $app['tschema']);
		$log_one .= ')';

		$log_many = rtrim($log_many, ', ');
		$log_str = 'Mass transaction from ';
		$log_str .= $to_one ? $log_many : $log_one;
		$log_str .= ' to ';
		$log_str .= $to_one ? $log_one : $log_many;

		$app['monolog']->info('trans: ' . $log_str, ['schema' => $app['tschema']]);

		if ($app['s_master'])
		{
			$app['alert']->warning('Master account: geen mails verzonden.');
		}
		else if ($mail_en)
		{
			foreach ($transactions as $transaction)
			{
				$user_id = $to_one ? $transaction['id_from'] : $transaction['id_to'];

				$vars = [
					'transaction'	=> $transaction,
					'from_user_id'	=> $transaction['id_from'],
					'to_user_id'	=> $transaction['id_to'],
					'user_id'		=> $user_id,
				];

				$app['queue.mail']->queue([
					'schema'	=> $app['tschema'],
					'to'		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
					'template'	=> 'transaction/transaction',
					'vars'		=> $vars,
				], random_int(0, 5000));
			}

			$vars = [
				'transactions'	=> $transactions,
				'total_amount'	=> $total_amount,
				'description'	=> $description,
			];

			if ($to_one)
			{
				$vars['to_user_id'] = $one_uid;
			}
			else
			{
				$vars['from_user_id'] = $one_uid;
			}

			$mail_template = 'mass_transaction/';
			$mail_template .= $to_one ? 'many_to_one' : 'one_to_many';

			$app['queue.mail']->queue([
				'schema'	=> $app['tschema'],
				'to' 		=> array_merge(
					$app['mail_addr_system']->get_admin($app['tschema']),
					$app['mail_addr_user']->get($app['s_id'], $app['tschema']),
					$app['mail_addr_user']->get($one_uid, $app['tschema'])
				),
				'template'	=> $mail_template,
				'vars'		=> $vars,
			], 8000);

			$app['alert']->success('Notificatie mails verzonden.');
		}

		cancel();
	}
}
else
{
	$mail_en = true;
}

$transid = $app['transaction']->generate_transid($app['s_id'], $app['server_name']);

if ($to_letscode)
{
	if ($to_name = $app['db']->fetchColumn('select name
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$to_letscode]))
	{
		$to_letscode .= ' ' . $to_name;
	}
}
if ($from_letscode)
{
	if ($from_name = $app['db']->fetchColumn('select name
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$from_letscode]))
	{
		$from_letscode .= ' ' . $from_name;
	}
}

$system_minlimit = $app['config']->get('minlimit', $app['tschema']);
$system_maxlimit = $app['config']->get('maxlimit', $app['tschema']);

$app['assets']->add([
	'typeahead',
	'typeahead.js',
	'mass_transaction.js',
	'combined_filter.js',
]);

$h1 = 'Massa transactie';
$fa = 'exchange';

include __DIR__ . '/include/header.php';

echo '<div class="panel panel-warning">';
echo '<div class="panel-heading">';
echo '<button class="btn btn-default" ';
echo 'title="Toon invul-hulp" data-toggle="collapse" ';
echo 'data-target="#help" type="button">';
echo '<i class="fa fa-question"></i>';
echo ' Invul-hulp</button>';
echo '</div>';
echo '<div class="panel-heading collapse" id="help">';

echo '<p>Met deze invul-hulp kan je snel alle ';
echo 'bedragen van de massa-transactie invullen. ';
echo 'De bedragen kan je nadien nog individueel ';
echo 'aanpassen alvorens de massa transactie uit te voeren. ';
echo '</p>';

echo '<form class="form" id="fill_in_aid">';

echo '<div class="pan-sub bg-warning">';

echo '<div class="form-group">';
echo '<label for="fixed" class="control-label">';
echo 'Vast bedrag</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $app['tschema']);
echo '</span>';
echo '<input type="number" class="form-control margin-bottom" id="fixed" ';
echo 'min="0">';
echo '</div>';
echo '</div>';

echo '</div>';

/**/
echo '<div class="pan-sub bg-warning">';

echo '<h4>Variabel deel</h4>';

//
echo '<div class="form-group">';
echo '<label for="fixed" class="control-label">';
echo 'Over periode</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo 'dagen</span>';
echo '<input type="number" ';
echo 'class="form-control margin-bottom" id="var_days" ';
echo 'min="0">';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_balance" class="control-label">';
echo 'Promille op saldo</label>';
echo '<div class="row">';
echo '<div class="col-sm-6">';

echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';
echo '<input type="number" ';
echo 'class="form-control margin-bottom" id="var_balance">';
echo '</div>';
echo '<p>Berekend op gewogen gemiddelde van saldo. ';
echo 'Kan ook negatief zijn!</p>';
echo '</div>';

echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $app['tschema']);
echo ': basis';
echo '</span>';
echo '<input type="number" class="form-control" id="var_base">';
echo '</div>';
echo '<p>De basis waartegenover berekend wordt. ';
echo 'Kan ook afwijkend van nul zijn.</p>';
echo '</div>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_trans_in" class="control-label">';
echo 'Promille op transacties in</label>';
echo '<div class="row">';
echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';
echo '<input type="number" class="form-control" id="var_trans_in">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo 'excl. ';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" ';
echo 'id="var_ex_code_in" ';
echo 'placeholder="Account Codes">';
echo '</div>';
echo '<p>Exclusief tegenpartijen: ';
echo 'Account Codes gescheiden door komma\'s</p>';
echo '</div>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_trans_out" class="control-label">';
echo 'Promille op transacties uit</label>';
echo '<div class="row">';
echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">&permil;</span>';
echo '<input type="number" class="form-control" id="var_trans_out">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo 'excl. ';
echo '<i class="fa fa-user"></i>';
echo '</span>';
echo '<input type="text" class="form-control" ';
echo 'id="var_ex_code_out" ';
echo 'placeholder="Account Codes">';
echo '</div>';
echo '<p>Exclusief tegenpartijen: ';
echo 'Account Codes gescheiden door komma\'s</p>';
echo '</div>';
echo '</div>';
echo '</div>';

//
echo '<div class="form-group">';
echo '<label for="var_minimum" class="control-label">';
echo 'Minimum - maximum</label>';
echo '<div class="row">';
echo '<div class="col-sm-6">';

echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $app['tschema']);
echo ': min';
echo '</span>';

echo '<input type="number" ';
echo 'class="form-control margin-bottom" id="var_min">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-6">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $app['tschema']);
echo ': max';
echo '</span>';
echo '<input type="number" class="form-control" id="var_max">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';
/**/

echo '<div class="form-group">';
echo '<label for="respect_minlimit" class="control-label">';
echo '<input type="checkbox" id="respect_minlimit" checked="checked">';
echo ' Respecteer minimum limieten</label>';
echo '</div>';

if ($app['config']->get('minlimit', $app['tschema']) !== ''
	|| $app['config']->get('maxlimit', $app['tschema']) !== '')
{
	echo '<ul>';

	if ($app['config']->get('minlimit', $app['tschema']) !== '')
	{
		echo '<li>Minimum Systeemslimiet: ';
		echo $app['config']->get('minlimit', $app['tschema']);
		echo ' ';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</li>';
	}

	if ($app['config']->get('maxlimit', $app['tschema']) !== '')
	{
		echo '<li>Maximum Systeemslimiet: ';
		echo $app['config']->get('maxlimit', $app['tschema']);
		echo ' ';
		echo $app['config']->get('currency', $app['tschema']);
		echo '</li>';
	}

	echo '<li>De Systeemslimieten gelden voor alle Accounts behalve de ';
	echo 'Accounts waarbij individuele limieten ingesteld zijn.</li>';

	echo '</ul>';
}

echo '<button class="btn btn-default" id="fill-in">';
echo 'Vul in</button>';

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
echo '<input type="text" class="form-control" ';
echo 'id="q" name="q" value="';
echo $q;
echo '">';
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
	$class_li = $shsh == $hsh ? ' class="active"' : '';
	$class_a  = $s['cl'] ?? 'white';

	echo '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
	echo 'data-filter="' . $shsh . '">' . $s['lbl'] . '</a></li>';
}

echo '</ul>';

echo '<form method="post" autocomplete="off">';

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="';
echo $hsh;
echo '" name="hsh" id="hsh">';
echo '<input type="hidden" value="" ';
echo 'name="selected_users" id="selected_users">';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

$typeahead_ary = [];

foreach (['active', 'inactive', 'ip', 'im', 'extern'] as $t_stat)
{
	$typeahead_ary[] = [
		'accounts', [
			'status'	=> $t_stat,
			'schema'	=> $app['tschema'],
		],
	];
}

echo '<div class="form-group">';
echo '<label for="from_letscode" class="control-label">';
echo 'Van Account Code';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-user"></span></span>';
echo '<input type="text" class="form-control" ';
echo 'id="from_letscode" name="from_letscode" ';
echo 'value="';
echo $from_letscode;
echo '" ';
echo 'data-newuserdays="';
echo $app['config']->get('newuserdays', $app['tschema']);
echo '" ';
echo 'data-typeahead="';
echo $app['typeahead']->get($typeahead_ary);
echo '">';
echo '</div>';
echo '<p>Gebruik dit voor een "Eén naar veel" transactie.';
echo 'Alle ingevulde bedragen hieronder ';
echo 'worden van dit Account gehaald.</p>';
echo '</div>';

echo '</div>';

echo '<table class="table table-bordered table-striped ';
echo 'table-hover panel-body footable" ';
echo 'data-filter="#combined-filter" data-filter-minimum="1" ';
echo 'data-minlimit="';
echo $system_minlimit;
echo '" ';
echo 'data-maxlimit="';
echo $system_maxlimit;
echo '"';
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
	$status_key = ($status_key == 'active' && $app['new_user_treshold'] < strtotime($user['adate'])) ? 'new' : $status_key;

	$hsh = $st[$status_key]['hsh'] ?: '';
	$hsh .= $status_key == 'leaving' || $status_key == 'new' ? $st['active']['hsh'] : '';
	$hsh .= $status_key == 'active' ? $st['without-new-and-leaving']['hsh'] : '';

	$class = isset($st[$status_key]['cl']) ? ' class="' . $st[$status_key]['cl'] . '"' : '';

	echo '<tr' . $class . ' data-user-id="' . $user_id . '">';

	echo '<td>';
	echo link_user($user, $app['tschema'], true, false, 'letscode');
	echo '</td>';

	echo '<td>';
	echo link_user($user, $app['tschema'], true, false, 'name');
	echo '</td>';

	echo '<td data-value="' . $hsh . '">';
	echo '<input type="number" name="amount[' . $user_id . ']" ';
	echo 'class="form-control" ';
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

	$minlimit = $user['minlimit'] === '' ? $system_minlimit : $user['minlimit'];
	$maxlimit = $user['maxlimit'] === '' ? $system_maxlimit : $user['maxlimit'];

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
echo '<label for="total" class="control-label">Totaal';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo $app['config']->get('currency', $app['tschema']);
echo '</span>';
echo '<input type="number" class="form-control" id="total" readonly>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="to_letscode" class="control-label">';
echo 'Aan Account Code';
echo '</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-user"></span></span>';
echo '<input type="text" class="form-control" ';
echo 'id="to_letscode" name="to_letscode" ';
echo 'value="';
echo $to_letscode;
echo '" ';
echo 'data-typeahead-source="from_letscode">';
echo '</div>';
echo '<p>Gebruik dit voor een "Veel naar één" transactie. ';
echo 'Bijvoorbeeld, een ledenbijdrage. ';
echo 'Alle ingevulde bedragen hierboven ';
echo 'gaan naar dit Account.</p>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="description" class="control-label">';
echo 'Omschrijving</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-pencil"></span></span>';
echo '<input type="text" class="form-control" id="description" ';
echo 'name="description" ';
echo 'value="';
echo $description;
echo '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="mail_en" class="control-label">';
echo '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
echo $mail_en ? ' checked="checked"' : '';
echo '>';
echo ' Verstuur notificatie mails</label>';
echo '</div>';

echo '<div class="form-group">';
echo '<label>';
echo '<input type="checkbox" name="verify" ';
echo 'value="1" required> ';
echo 'Ik heb nagekeken dat de juiste ';
echo 'bedragen en de juiste "Van" of "Aan" ';
echo 'Account Code ingevuld zijn.';
echo '</label>';
echo '</div>';

echo aphp('transactions', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
echo '<input type="submit" value="Massa transactie uitvoeren" ';
echo 'name="zend" class="btn btn-success">';
echo $app['form_token']->get_hidden_input();

echo '</div>';
echo '</div>';

echo '</div>';

echo '<input type="hidden" value="';
echo $transid;
echo '" name="transid">';

echo '</form>';

include __DIR__ . '/include/footer.php';

/**
 *
 */
function mail_mass_transaction($mail_ary)
{
	global $app;

	if (!$app['config']->get('mailenabled', $app['tschema']))
	{
		$app['alert']->warning('Mail functions are not enabled. ');
		return;
	}

	$trans_map = [];

	$trans = $app['db']->executeQuery('select id, transid
		from ' . $app['tschema'] . '.transactions
		where transid in (?)',
		[$mail_ary['transid_ary']],
		[\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);

	foreach ($trans as $t)
	{
		$trans_map[$t['transid']] = $t['id'];
	}

	$from_many_bool = is_array($mail_ary['from']) ? true : false;

	$many_ary = $from_many_bool ? $mail_ary['from'] : $mail_ary['to'];

	$many_user_ids = array_keys($many_ary);

	$one_user_id = $from_many_bool ? $mail_ary['to'] : $mail_ary['from'];

	$from_user_id = $to_user_id = $one_user_id;

	$users = $app['db']->executeQuery('select u.id
		from ' . $app['tschema'] . '.users u
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

		$vars = [
			'amount' 			=> $many_ary[$user_id]['amount'],
			'transid' 			=> $many_ary[$user_id]['transid'],
			'from_user_id' 		=> $from_user_id,
			'to_user_id'		=> $to_user_id,
			'transaction_id'	=> $trans_map[$many_ary[$user_id]['transid']],
			'user_id'			=> $user_id,
		];

		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to'		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
			'template'	=> 'transaction/transaction',
			'vars'		=> $vars,
		], random_int(0, 5000));
	}

	$total_amount = 0;

	$users = [];

	$user_ids = $app['db']->executeQuery('select u.id
		from ' . $app['tschema'] . '.users u
		where u.id in (?)',
		[$many_user_ids],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($user_ids as $u)
	{
		$user_id = $u['id'];

		$users[] = [
			'amount'	=> $many_ary[$user_id]['amount'],
			'id'		=> $user_id,
		];

		$total_amount += $many_ary[$user_id]['amount'];
	}

	$vars = array_merge($common_vars, [
		'users'		=> $users,
		'user'		=> [
			'url'	=> $app['base_url'] . '/users.php?id=' . $one_user_id,
			'text'	=> link_user($one_user_id, $app['tschema'], false),
		],
		'total'		=> $total_amount,
	]);

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to' 		=> array_merge(
			$app['mail_addr_system']->get_admin($app['tschema']),
			$app['mail_addr_user']->get($app['s_id'], $app['tschema']),
			$app['mail_addr_user']->get($one_user_id, $app['tschema'])
		),
		'subject' 	=> $subject,
		'template'	=> 'admin_mass_transaction',
		'vars'		=> $vars,
	], 8000);

	return true;
}

function cancel():void
{
	header('Location: ' . generate_url('mass_transaction', []));
	exit;
}
