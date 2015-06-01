<?php
ob_start();

$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath.'includes/inc_adoconnection.php';

require_once $rootpath.'includes/inc_transactions.php';
require_once $rootpath.'includes/inc_request.php';
require_once $rootpath.'includes/inc_data_table.php';

$req = new request();
$req->add('fixed', 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 4, 'maxlength' => 4, 'label' => 'Vast bedrag'), array('match' => 'positive'))
	->add('percentage', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage op saldo'))
	->add('percentage_base', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Basis voor percentage op saldo'))
	->add('percentage_transactions', 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 4, 'maxlength' => 4, 'label' => 'Percentage op uitgeschreven transacties'))
	->add('percentage_transactions_days', 0, 'post', array('type' => 'number', 'size' => 4, 'maxlength' => 4, 'label' => 'Periode in dagen voor het percentage op uitgeschreven transacties.'))
	->add('fill_in', '', 'post', array('type' => 'submit', 'label' => 'Vul in'))
	->add('no_newcomers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen instappers.'), array())
	->add('no_leavers', '', 'post', array('type' => 'checkbox', 'label' => 'Geen uitstappers.'), array())
	->add('no_min_limit', '', 'post', array('type' => 'checkbox', 'label' => 'Geen saldo\'s onder de minimum limiet.'), array())
	->add('letscode_to', '', 'post', array('type' => 'text', 'size' => 5, 'maxlength' => 10, 'autocomplete' => 'off', 'label' => 'Aan LetsCode'), array('not_empty' => true, 'match' => 'existing_letscode'))
	->add('description', '', 'post', array('type' => 'text', 'size' => 40, 'maxlength' => 60, 'autocomplete' => 'off', 'label' => 'Omschrijving'), array('not_empty' => true))
	->add('confirm_password', '', 'post', array('type' => 'password', 'size' => 10, 'maxlength' => 20, 'autocomplete' => 'off', 'label' => 'Paswoord (extra veiligheid)'), array('not_empty' => true, 'match' => 'password'))
	->add('zend', '', 'post', array('type' => 'submit', 'label' => 'Voer alle transacties uit.'))
	->add('transid', generate_transid(), 'post', array('type' => 'hidden'));

$active_users = $db->GetArray(
	'SELECT id, fullname, letscode,
		accountrole, status, saldo, minlimit, maxlimit, adate
	FROM users
	WHERE status IN (1, 2)
	ORDER BY letscode');

$letscode_to = $req->get('letscode_to');
$to_user_id = null;

foreach($active_users as $user){
	$req->add('amount-'.$user['id'], 0, 'post', array('type' => 'number', 'min' => 0, 'size' => 3, 'maxlength' => 3, 'onkeyup' => 'recalc_table_sum(this);'), array('match' => 'positive'));
	if ($letscode_to && $user['letscode'] == $letscode_to){
		$to_user_id = $user['id'];
		$to_user_fullname = $user['fullname'];
	}
}

if ($req->get('zend') && $req->errors())
{
	$alert->error('Eén of meerdere velden in het formulier zijn niet correct ingevuld (zie onder).');
	$form_errors = true;
}

if ($req->get('zend') && !(isset($form_errors)) && $to_user_id)
{
	$description = $req->get('description');
	$transid = $req->get('transid');
	$date = date('Y-m-d H:i:s');

	$mail_ary = array(
		'to' 			=> $to_user_id,
		'description'	=> $description,
		'date'			=> $date,
	);

	if (check_duplicate_transaction($transid))
	{
		$alert->error('Een dubbele boeking van een transactie werd voorkomen.');
	}
	else
	{
		foreach($active_users as $user)
		{
			$amount = $req->get('amount-'.$user['id']);
			if (!$amount || $to_user_id == $user['id'])
			{
				continue;
			}
			$trans = array(
				'id_to' 		=> $to_user_id,
				'id_from' 		=> $user['id'],
				'amount' 		=> $amount,
				'description' 	=> $description,
				'date' 			=> $date,
				'transid'		=> $transid,
			);
			$notice_text = 'Transactie van gebruiker '.$user['fullname'].' ( '.$user['letscode'].' ) naar '.$to_user_fullname.' ( '.$letscode_to.' ) met bedrag '.$amount.' ';
			if(insert_transaction($trans))
			{
				//mail_masstransaction($trans);
				$mail_ary['from'][$user['id']] = array(
					'amount'	=> $amount,
					'transid' 	=> $transid,
				);

				$alert->success($notice_text . ' opgeslagen');
			}
			else
			{
				$alert->error($notice_text . ' mislukt');
			}
			$transid = generate_transid();
		}
		mail_mass_transaction($mail_ary);
	}
	$req->set('letscode_to', '');
	$req->set('description', '');
}

$fixed = $req->get('fixed');
$percentage = $req->get('percentage');
$percentage_base = $req->get('percentage_base');
$perc = 0;

$percentage_transactions = $req->get('percentage_transactions');
$percentage_transactions_days = $req->get('percentage_transactions_days');

if ($percentage_transactions && $percentage_transactions_days)
{
	$refdate = date('Y-m-d H:i:s', time() - (86400 * $percentage_transactions_days));
	$user_trans = $db->GetAssoc('SELECT tr.id_from, SUM(tr.amount) 
		FROM transactions tr, users u
		WHERE tr.cdate > \'' . $refdate . '\'
			AND u.id = tr.id_from
			AND u.status IN (1, 2)
		GROUP BY tr.id_from');
}

if ($req->get('fill_in') && ($fixed || $percentage || $user_trans))
{
	$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;
	foreach ($active_users as $user)
	{
		if ($user['letscode'] == $req->get('letscode_to')
			|| ($newusertreshold < strtotime($user['adate'])
				&& $req->get('no_newcomers'))
			|| ($user['status'] == 2 && $req->get('no_leavers'))
			|| ($user['saldo'] < $user['minlimit'] && $req->get('no_min_limit')))
		{
			$req->set('amount-'.$user['id'], 0);
		}
		else
		{
			if ($percentage)
			{
				$perc = round(($user['saldo'] - $percentage_base)*$percentage/100);
				$perc = ($perc > 0) ? $perc : 0;
			}
			$perc_trans = (isset($user_trans[$user['id']])) ? round($user_trans[$user['id']] * $percentage_transactions / 100) : 0;
			$amount = $fixed + $perc + $perc_trans;
			$req->set('amount-'.$user['id'], $amount);
		}
	}
}

$req->set('amount-'.$to_user_id, 0);
$req->set('confirm_password', '');

$data_table = new data_table();
$data_table->set_data($active_users)->set_input($req)
	->add_column('letscode', array('title' => 'Van LetsCode', 'render' => 'status'))
	->add_column('fullname', array('title' => 'Naam'))
	->add_column('accountrole', array('title' => 'Rol', 'footer_text' => 'TOTAAL'))
	->add_column('saldo', array('title' => 'Saldo', 'footer' => 'sum', 'render' => 'limit'))
	->add_column('amount', array('title' => 'Bedrag', 'input' => 'id', 'footer' => 'sum'))
	->add_column('minlimit', array('title' => 'Min.Limiet'));

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/mass_transaction_add.js"></script>';

$h1 = 'Massa transactie: "veel naar één"';

include $rootpath . 'includes/inc_header.php';

echo '<div style="background-color:#ffdddd; padding:10px;">';
echo '<h2>Invul-hulp</h2>';
echo '<p>Met deze invul hulp kan je snel alle bedragen van de massa-transactie invullen. ';
echo 'De eigenlijke massa-transactie doe je met het gele formulier onderaan. Daar zie ook de ';
echo 'feitelijk bedragen die zullen worden overgeschreven. Je kan daar de bedragen alvorens nog individueel ';
echo 'aanpassen.</p>';
echo '<table  cellspacing="5" cellpadding="0" border="0">';
$req->set_output('tr')->render(array(
	'fixed', 'percentage', 'percentage_base',
	'percentage_transactions', 'percentage_transactions_days',
	'no_newcomers', 'no_leavers', 'no_min_limit', 'fill_in'));
echo '</table>';
echo '<p><strong><i>Aan LETSCode</i></strong> wordt altijd automatisch overgeslagen. Alle bedragen blijven individueel aanpasbaar alvorens de massa-transactie uitgevoerd wordt.</p>';
echo '<p>Deze mogelijkheden zijn combineerbaar:';
echo '<ul><li><strong>Vast bedrag</strong></li>';
echo '<li><strong>Percentage op saldo</strong> (Het percentage kan ook negatief zijn.) ';
echo 'De basis zal gewoonlijk nul zijn, maar er het is ook mogelijk een percentage te berekenen t.o.v. een ander bedrag.</li>';
echo '<li><strong>Percentage op uitgeschreven transacties</strong> Percentage en aantal dagen dienen beide ingevuld te zijn indien men deze optie wil gebruiken.</li>';
echo '</ul></div>';

echo '<div class="panel panel-default">';
echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="letscode_to" class="col-sm-2 control-label">Aan letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode_to" name="letscode_to" ';
echo 'value="' . $transaction['letscode_to'] . '" required>';
echo '</div>';
echo '</div>';

echo '</div>';



echo '<div id="transformdiv" style="padding:10px;">';
$data_table->render();
echo '<table cellspacing="0" cellpadding="5" border="0">';
$req->set_output('tr')->render(array('letscode_to', 'description', 'confirm_password', 'zend', 'transid'));
echo '</table></div></form>';

include $rootpath . 'includes/inc_footer.php';

function check_newcomer($adate)
{
	return  (time() - (readconfigfromdb('newuserdays') * 86400) < strtotime($adate)) ? true : false;
}

function mail_mass_transaction($mail_ary)
{
	global $db, $alert, $s_id;

	if (!readconfigfromdb('mailenabled'))
	{
		$alert->warning('Mail functions are not enabled. ');
		return;
	}
	
	$from = readconfigfromdb("from_address_transactions");
	if (empty($from))
	{
		$alert->warning('Mail from_address_transactions is not set in configuration');
		return;
	}

	$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
	$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];	
	$base_url = $http . $_SERVER['SERVER_NAME'] . $port;

	$from_many_bool = (is_array($mail_ary['from'])) ? true : false;

	$many_ary = ($from_many_bool) ? $mail_ary['from'] : $mail_ary['to'];
	$many_user_ids = array_keys($many_ary);

	$one_user_id = ($from_many_bool) ? $mail_ary['to'] : $mail_ary['from'];

	$one_user = $db->GetRow('select u.id, u.fullname, u.letscode, c.value as mail
		from users u, contact c, type_contact tc
		where u.id = ' . $one_user_id . '
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'');

	$mailaddr = $db->GetAssoc('select u.id, c.value
		from users u, contact c, type_contact tc
		where u.status in (1, 2)
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'mail\'');

	$r = "\r\n";
	$currency = readconfigfromdb('currency');
	$support = readconfigfromdb('support');
	$login_url = $base_url . '/login.php?login=*|LOGIN|*';
	$new_transaction_url = $base_url . '/transactions/add.php';

	$to = $merge_vars  = array();
	$to_log = '';
	$total = 0;
	$t = 'Dit is een automatisch gegenereerde mail. Niet beantwoorden a.u.b.';
	$t_one = $one_user['fullname'] . '(' . $one_user['letscode'] . ')';

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

	$query = 'SELECT u.id,
			u.name, u.saldo, u.status, u.minlimit, u.maxlimit,
			u.fullname, u.letscode, u.login
		FROM users u
		WHERE u.status in (1, 2)
			AND u.id';
	$query .= (count($many_user_ids) > 1) ? ' IN (' . implode(', ', $many_user_ids) . ')' : ' = ' . $many_user_ids[0];
			
	$rs = $db->Execute($query);

	while ($user = $rs->FetchRow())
	{
		$amount = $many_ary[$user['id']]['amount'];
		$transid = $many_ary[$user['id']]['transid'];

		$one_msg .= $user['letscode'] . ' ' . $user['fullname'] . ': ';
		$one_msg .= $amount . ' ' . $currency;
		$one_msg .= ', transactie-id: ' . $transid . $r;

		$total += $amount;
		$to_log .= $user['fullname'] . '(' . $user['letscode'] . '), ';

		$to[] = array(
			'email'	=> $mailaddr[$user['id']],
			'name'	=> $user['fullname'],
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
					'name'		=> 'FULLNAME',
					'content'	=> $user['fullname'],
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

	$text .= 'Notificatie transactie' . $r;
	$html .= '<h2>Notificatie transactie</h2>';

	$t_many = '*|FULLNAME|* (*|LETSCODE|*)';

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

	$text .=  $t . $r;
	$html .= '<p>' . $t . '</p>';

	$t = 'Transactie id: *|TRANSID|*';

	$text .=  $t . $r;
	$html .= '<p>' . $t . '</p>';

	$t = 'Tijdstip: ' . $mail_ary['date'];

	$text .=  $t . $r . $r;
	$html .= '<p>' . $t . '</p><br>';

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

	$subject = '[eLAS-'. readconfigfromdb('systemtag') .'] - Nieuwe transactie.';

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
		return;
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	$subject = '[eLAS-'. readconfigfromdb('systemtag') .'] - Nieuwe massa transactie.';

	log_event($s_id, 'Mail', 'Massa transaction mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to_log);

	$message = array(
		'subject'		=> $subject,
		'text'			=> $one_msg,
		'from_email'	=> $from,
		'to'			=> array(array(
			'name' 	=> $one_user['fullname'],
			'email'	=> $one_user['mail'],
		)),
		'merge_vars'	=> $merge_vars,
	);

	$mandrill->messages->send($message, true);

	return true;
}
