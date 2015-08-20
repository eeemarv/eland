<?php
ob_start();

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

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
echo '<label for="trans_base" class="col-sm-3 control-label">';
echo 'Enkel wanneer account is boven bedrag (' . $currency . ')</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="trans_base" name="trans_base" ';
echo 'value="' . $a['trans_base'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<h4>2. Tijd actief (éénmalig)</h4>';

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
echo '<label for="inactivity_days" class="col-sm-3 control-label">';
echo 'Dagen</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="inactivity_days" name="inactivity_days" ';
echo 'value="' . $a['inactivity_days'] . '" ';
echo 'class="form-control" min="0">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="inactivity_max_trans_count" class="col-sm-3 control-label">';
echo 'Minimum aantal ontvangen transacties</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="inactivity_max_trans_count" name="inactivity_max_trans_count" ';
echo 'value="' . $a['min_transactions'] . '" ';
echo 'class="form-control" min="0">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="inactivity_max_trans_count" class="col-sm-3 control-label">';
echo 'Minimum aantal ontvangen transacties</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="inactivity_max_trans_count" name="inactivity_max_trans_count" ';
echo 'value="' . $a['min_transactions'] . '" ';
echo 'class="form-control" min="0">';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Tegenpartij transacties</h3>';
echo '<p>Dit geeft de mogelijkheid om bepaalde tegenpartijen uit ';
echo 'te sluiten in de transacties in de automatische minimum limiet triggers boven.</p>';

echo '<div class="form-group">';
echo '<label for="trans_exclusive" class="col-sm-3 control-label">';
echo 'Exclusief tegenpartijen (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
echo 'value="' . $a['trans_exclusive'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
