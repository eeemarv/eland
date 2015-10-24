<?php

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
$del_q = ($_GET['del_q']) ? true : false;

$submit = ($_POST['zend']) ? true : false;

$currency = readconfigfromdb('currency');

/**
 * delete interlets queue
 */
if ($del_q)
{
	$s_owner = ($uid && $uid == $s_id) ? true : false;

	if (!($s_admin || $s_owner))
	{
		$alert->error('Je hebt onvoldoende rechten voor deze actie.');
		cancel();
	}

	if ($uid)
	{
		if (!$db->fetchColumn('select transid from interletsq where id_from = ?', array($uid)))
		{
			$alert->error('Er zijn geen interlets tranacties van ' . link_user($uid, null, false) . ' in verwerking.');
			cancel();
		}
	}
	else
	{
		if (!$db->fetchColumn('select transid from interletsq'))
		{
			$alert->error('Er zijn geen interlets transacties in verwerking.');
			cancel();
		}
	}

	if ($submit)
	{
		if ($uid)
		{
			if ($db->delete('interletsq', array('id_from' => $uid)))
			{
				$alert->success('De interlets transacties in verwerking van ' . link_user($uid, null, false) . ' zijn verwijderd.');
			}
			else
			{
				$alert->error('Fout bij het verwijderen van de interlets transacties in verwerking van ' . link_user($uid, null, false));
			}
		}
		else
		{
			if ($db->executeUpdate('delete from interletsq'))
			{
				$alert->success('De interlets transacties in verwerking zijn verwijderd.');
			}
			else
			{
				$alert->error('Fout bij het verwijderen van de interlets transacties in verwerking.
					Mogelijks werd de laatste transactie in verwerking reeds uitgevoerd.');
			}
		}
		cancel();
	}

	$from = ($uid) ? ' van ' . link_user($uid) : '';

	$h1 = 'Verwijderen interlets transacties' . $from . ' in verwerking?';
	$fa = 'times';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<a href="' . $rootpath . 'transactions.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Verwijderen" class="btn btn-danger">';

	echo '</form>';
	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * add
 */
if ($add)
{
	if ($s_guest)
	{
		$alert->error('Je hebt geen rechten om een transactie toe te voegen.');
		cancel();
	}

	$transaction = array();

	$redis_transid_key = $schema . '_transid_u_' . $s_id;

	if ($submit)
	{
		$errors = array();

		$stored_transid = $redis->get($redis_transid_key);

		if (!$stored_transid)
		{
			$errors[] = 'Formulier verlopen.';
		}

		$transaction['transid'] = $_POST['transid'];
		$transaction['description'] = $_POST['description'];
		list($letscode_from) = explode(' ', $_POST['letscode_from']);
		list($letscode_to) = explode(' ', $_POST['letscode_to']);
		$transaction['amount'] = $amount = $_POST['amount'];
		$transaction['date'] = date('Y-m-d H:i:s');
		$letsgroup_id = $_POST['letsgroup_id'];

		if ($stored_transid != $transaction['transid'])
		{
			$errors[] = 'Fout transactie id.';
		}

		if ($db->fetchColumn('select transid from transactions where transid = ?', array($stored_transid)))
		{
			$errors[] = 'Een herinvoer van de transactie werd voorkomen.';
		}

		if ($letsgroup_id != 'self')
		{
			$letsgroup = $db->fetchAssoc('SELECT * FROM letsgroups WHERE id = ?', array($letsgroup_id));

			if (!isset($letsgroup))
			{
				$alert->error('Letsgroep niet gevonden.');
			}
		}

		if ($s_user)
		{
			$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE id = ?', array($s_id));
		}
		else
		{
			$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_from));
		}

		$letscode_touser = ($letsgroup_id == 'self') ? $letscode_to : $letsgroup['localletscode'];
		$touser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode_touser));

		$transaction['id_from'] = $fromuser['id'];
		$transaction['id_to'] = $touser['id'];

		list($schemas, $domains) = get_schemas_domains(true);

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

		if(($touser['saldo'] + $transaction['amount']) > $touser['maxlimit'] && !$s_admin)
		{
			$t_account = ($letsgroup_id == 'self') ? 'bestemmeling' : 'interletsrekening';
			$errors[] = 'De ' . $t_account . ' heeft zijn maximum limiet bereikt.';
		}

		if($letsgroup_id == 'self'
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

		$contact_admin = ($s_admin) ? '' : ' Contacteer een admin.';

		if(!empty($errors))
		{
			$alert->error(implode('<br>', $errors));
		}
		else if ($letsgroup_id == 'self')
		{
			if ($id = insert_transaction($transaction))
			{
				$transaction['id'] = $id;
				mail_transaction($transaction);
				$alert->success('Transactie opgeslagen');
			}
			else
			{
				$alert->error('Gefaalde transactie');
			}
			cancel();
		}
		else if ($letsgroup['apimethod'] == 'mail')
		{
			if ($id = insert_transaction($transaction))
			{
				$transaction['id'] = $id;
				$transaction['letscode_to'] = $letscode_to;

				mail_mail_interlets_transaction($transaction);

				$alert->success('Interlets transactie opgeslagen (verwerking per mail).');
			}
			else
			{
				$alert->error('Gefaalde interlets transactie');
			}
			cancel();
		}
		else if ($letsgroup['apimethod'] != 'elassoap')
		{
			$alert->error('Deze interlets groep heeft geen geldige api methode.' . $contact_admin);
			cancel();
		}
		else if (!$letsgroup['url'])
		{
			$alert->error('Geen url voor deze interlets groep.' . $contact_admin);
			cancel();
		}
		else if (!($remote_schema = $schemas[$letsgroup['url']]))
		{
			// The interlets letsgroup is on another server, use elassoap; queue the transaction.

			if (!$letsgroup['remoteapikey'])
			{
				$alert->error('Geen apikey voor deze interlets groep ingesteld.' . $contact_admin);
				cancel();
			}

			if (!$letsgroup['presharedkey'])
			{
				$alert->error('Geen preshared key voor deze interlets groep ingesteld.' . $contact_admin);
				cancel();
			}

			if (!$letsgroup['myremoteletscode'])
			{
				$alert->error('Geen remote letscode ingesteld voor deze interlets groep.' . $contact_admin);
				cancel();
			}

			$transaction['letscode_to'] = $letscode_to;
			$transaction['letsgroup_id'] = $letsgroup_id;
			$currencyratio = readconfigfromdb('currencyratio');
			$transaction['amount'] = $transaction['amount'] / $currencyratio;
			$transaction['amount'] = (float) $transaction['amount'];
			$transaction['amount'] = round($transaction['amount'], 5);
			$transaction['signature'] = sign_transaction($transaction, $letsgroup['presharedkey']);
			$transaction['retry_until'] = gmdate('Y-m-d H:i:s', time() + 86400);

			unset($transaction['date'], $transaction['id_to']);

			$transaction['retry_count'] = 0;
			$transaction['last_status'] = 'NEW';

			if ($db->insert('interletsq', $transaction))
			{
				if (!$redis->get($schema . '_interletsq'))
				{
					$redis->set($schema . '_interletsq', time());
				}
				$alert->success('Interlets transactie in verwerking');
				cancel();
			}

			$alert->error('Gefaalde queue interlets transactie');
			cancel();
		}
		else
		{
			// the interlets letsgroup is on the same server

			$to_remote_user = $db->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', array($letscode_to));

			if (!$to_remote_user)
			{
				$alert->error('De interlets gebruiker bestaat niet.');
				cancel();
			}

			if (!in_array($to_remote_user['status'], array('1', '2')))
			{
				$alert->error('De interlets gebruiker is niet actief.');
				cancel();
			}

			$remote_letsgroup = $db->fetchAssoc('select *
				from ' . $remote_schema . '.letsgroups
				where url = ?', array($base_url));

			$systemname = readconfigfromdb('systemname');

			if (!$remote_letsgroup)
			{
				$alert->error('De remote interlets groep heeft deze letsgroep ('. $systemname . ') niet geconfigureerd.');
				cancel();
			}

			if (!$remote_letsgroup['localletscode'])
			{
				$alert->error('Er is geen interlets account gedefiniëerd in de remote interlets groep.');
				cancel();
			}

			$remote_interlets_account = $db->fetchAssoc('select *
				from ' . $remote_schema . '.users
				where letscode = ?', array($remote_letsgroup['localletscode']));

			if (!$remote_interlets_account)
			{
				$alert->error('Er is geen interlets account in de remote interlets group.');
				cancel();
			}

			if ($remote_interlets_account['accountrole'] != 'interlets')
			{
				$alert->error('Het interlets account in de remote interlets groep heeft geen juiste rol. Deze moet van het type interlets zijn.');
				cancel();
			}

			if (!in_array($remote_interlets_account['status'], array(1, 2, 7)))
			{
				$alert->error('Het interlets account in de remote interlets groep heeft geen juiste status. Deze moet van het type extern, actief of uitstapper zijn.');
				cancel();
			}

			$remote_currency = readconfigfromschema('currency', $remote_schema);
			$remote_currencyratio = readconfigfromschema('currencyratio', $remote_schema);
			$currencyratio = readconfigfromdb('currencyratio');

			$remote_amount = round(($transaction['amount'] * $remote_currencyratio) / $currencyratio);

			if(($remote_interlets_account['saldo'] - $remote_amount) < $remote_interlets_account['minlimit'])
			{
				$alert->error('De interlets account van de remote interlets groep heeft onvoldoende saldo beschikbaar.');
				cancel();
			}

			if(($to_remote_user['saldo'] + $remote_amount) > $to_remote_user['maxlimit'])
			{
				$alert->error('De interlets gebruiker heeft zijn maximum limiet bereikt.');
				cancel();
			}

			//
			$transaction['creator'] = (empty($s_id)) ? 0 : $s_id;
			$transaction['cdate'] = date('Y-m-d H:i:s');
			$transaction['real_to'] = $to_remote_user['letscode'] . ' ' . $to_remote_user['name'];

			$db->beginTransaction();
			try
			{
				$db->insert('transactions', $transaction);
				$id = $db->lastInsertId('transactions_id_seq');
				$db->executeUpdate('update users
					set saldo = saldo + ? where id = ?',
					array($transaction['amount'], $transaction['id_to']));
				$db->executeUpdate('update users
					set saldo = saldo - ? where id = ?',
					array($transaction['amount'], $transaction['id_from']));

				$trans_org = $transaction;
				$trans_org['id'] = $id;

				$transaction['creator'] = 0;
				$transaction['amount'] = $remote_amount;
				$transaction['id_from'] = $remote_interlets_account['id'];
				$transaction['id_to'] = $to_remote_user['id'];
				$transaction['real_from'] = link_user($fromuser['id'], null, false);
				unset($transaction['real_to']);

				$db->insert($remote_schema . '.transactions', $transaction);
				$id = $db->lastInsertId($remote_schema . '.transactions_id_seq');
				$db->executeUpdate('update ' . $remote_schema . '.users
					set saldo = saldo + ? where id = ?',
					array($remote_amount, $transaction['id_to']));
				$db->executeUpdate('update ' . $remote_schema . '.users
					set saldo = saldo - ? where id = ?',
					array($transaction['amount'], $transaction['id_from']));
				$transaction['id'] = $id;

				$db->commit();

			}
			catch(Exception $e)
			{
				$db->rollback();
				$alert->error('Transactie niet gelukt.');
				throw $e;
				exit;
			}

			readuser($fromuser['id'], true);
			readuser($touser['id'], true);

			readuser($remote_interlets_account['id'], true, $remote_schema);
			readuser($to_remote_user['id'], true, $remote_schema);

			mail_transaction($trans_org);
			mail_transaction($transaction, $remote_schema);

			log_event($s_id, 'trans', 'direct interlets transaction ' . $transaction['transid'] . ' amount: ' .
				$amount . ' from user: ' .  link_user($fromuser['id'], null, false) .
				' to user: ' . link_user($touser['id'], null, false));

			log_event('', 'trans', 'direct interlets transaction (receiving) ' . $transaction['transid'] .
				' amount: ' . $remote_amount . ' from user: ' . $remote_interlets_account['letscode'] . ' ' .
				$remote_interlets_account['name'] . ' to user: ' . $to_remote_user['letscode'] . ' ' .
				$to_remote_user['name'], $remote_schema);

			autominlimit_queue($transaction['id_from'], $transaction['id_to'], $remote_amount, $remote_schema);

			$alert->success('Interlets transactie uitgevoerd.');
			cancel();
		}

		$transaction['letscode_to'] = $_POST['letscode_to'];
		$transaction['letscode_from'] = ($s_admin) ? $_POST['letscode_from'] : $s_letscode . ' ' . $s_name;
	}
	else
	{
		//GET form

		$transid = generate_transid();

		$redis->set($redis_transid_key, $transid);
		$redis->expire($redis_transid_key, 3600);

		$transaction = array(
			'date'			=> date('Y-m-d'),
			'letscode_from'	=> $s_letscode . ' ' . $s_name,
			'letscode_to'	=> '',
			'amount'		=> '',
			'description'	=> '',
			'transid'		=> $transid,
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

	if (!isset($_POST['zend']))
	{
		$letsgroup['id'] = 'self';
	}

	$thumbprint = getenv('ELAS_DEBUG') ? time() : round((time() / 900) * 900);

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/transactions_add.js"></script>';

	$user = readuser($s_id);
	$balance = $user['saldo'];

	$letsgroups = $db->fetchAll('SELECT id, groupname, url FROM letsgroups where apimethod <> \'internal\'');
	$letsgroups = array_merge(array(array(
			'groupname' => readconfigfromdb('systemname'),
			'id'		=> 'self',
		)), $letsgroups);

	$currency = readconfigfromdb('currency');

	$top_buttons .= '<a href="' . $rootpath . 'transactions.php" class="btn btn-default"';
	$top_buttons .= ' title="Transactielijst"><i class="fa fa-exchange"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'transactions.php?uid=' . $s_id . '" class="btn btn-default"';
	$top_buttons .= ' title="Mijn transacties"><i class="fa fa-user"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';

	$h1 = 'Nieuwe transactie';
	$fa = 'exchange';

	include $rootpath . 'includes/inc_header.php';

	$minlimit = $user['minlimit'];

	echo '<div>';
	echo '<p><strong>' . link_user($user) . ' huidige ' . $currency . ' stand: ';
	echo '<span class="label label-info">' . $balance . '</span></strong> ';
	echo '<strong>Minimum limiet: <span class="label label-danger">' . $minlimit . '</span></strong></p>';
	echo '</div>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form  method="post" class="form-horizontal">';

	echo ($s_admin) ? '' : '<div style="display:none;">';

	echo '<div class="form-group"';
	echo ($s_admin) ? '' : ' disabled="disabled" ';
	echo '>';
	echo '<label for="letscode_from" class="col-sm-2 control-label">';
	echo '<span class="label label-info">Admin</span> ';
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
		echo '<option value="' . $l['id'] . '" ';
		echo 'data-thumbprint="' . $thumbprint . '"';
		echo ($l['id'] == $letsgroup['id']) ? ' selected="selected"' : '';
		echo ($l['id'] == 'self') ? ' data-this-letsgroup="1"' : '';
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

	echo '<input type="hidden" name="transid" value="' . $transaction['transid'] . '">';

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

$h1 = ($uid && $inline) ? '<a href="' . $rootpath . 'transactions.php?uid=' . $uid . '">' : '';
$h1 .= 'Transacties';
$h1 .= ($uid && $inline) ? '</a>' : '';
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

if ($uid)
{
	$interletsq = $db->fetchAll('select q.*, l.groupname
		from interletsq q, letsgroups l
		where q.id_from = ?
			and q.letsgroup_id = l.id', array($uid));
	$from = ' van ' . link_user($uid);
}
else
{
	$interletsq = $db->fetchAll('select q.*, l.groupname
		from interletsq q, letsgroups l
		where q.letsgroup_id = l.id');
	$from = '';
}

if (count($interletsq))
{
	if ($s_admin || ($uid && ($uid == $s_id)))
	{
		$and_uid = ($uid) ? '&uid=' . $uid : '';

		$q_buttons .= '<a href="' . $rootpath . 'transactions.php?del_q=1' . $and_uid . '" ';
		$q_buttons .= 'class="btn btn-danger"';
		$q_buttons .= ' title="Verwijder interlets transacties in verwerking"><i class="fa fa-times"></i>';
		$q_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
	}

	echo '<h3><span class="fa fa-exchange"></span> InterLETS transacties' . $from . ' in verwerking';
	echo '<span class="inline-buttons"> ' . $q_buttons . '</span>';
	echo '</h3>';

	echo '<div class="panel panel-warning">';
	echo '<div class="table-responsive">';
	echo '<table class="table table-hover table-striped table-bordered footable">';

	echo '<thead>';
	echo '<tr class="warning">';
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
		echo '<tr class="warning">';

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
		echo link_user($q['id_from']);
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
	echo '</table></div></div>';
}

if (!$inline)
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $rootpath;

	header('Location: ' . $rootpath . 'transactions.php' . (($id) ? '?id=' . $id : ''));
	exit;
}
